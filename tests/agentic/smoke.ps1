<#
Agentic checkout smoke test against wp-env (Windows PowerShell 5.1, curl.exe).

  .\tests\agentic\smoke.ps1                      # BTC: cart -> checkout -> details -> quote reuse
  .\tests\agentic\smoke.ps1 -SimulateCallback    # + fake Blockonomics callbacks (status 0 then 2)
  .\tests\agentic\smoke.ps1 -Crypto usdt -TxHash 0xabc...   # + agent txhash submit (hits real monitor_tx)
#>
param(
    [string]$Base = 'http://localhost:8888',
    [ValidateSet('btc', 'usdt', 'bch')][string]$Crypto = 'btc',
    [string]$ProductSlug = 'agent-test-item',
    [switch]$SimulateCallback,
    [double]$PayRatio = 1,  # <1 simulates underpayment in the final callback
    [string]$TxHash = '',
    [string]$Secret = ''  # remote host: pass blockonomics_callback_secret instead of reading it from wp-env
)

$ErrorActionPreference = 'Stop'
$script:CartToken = $null
$tmp = Join-Path $env:TEMP ('agentic-smoke-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path $tmp | Out-Null

function Invoke-Api([string]$Method, [string]$Path, $Body = $null) {
    $hdr = Join-Path $tmp 'headers.txt'
    $cargs = @('-s', '-X', $Method, '-D', $hdr, '-H', 'Content-Type: application/json', '-w', '\n%{http_code}')
    if ($script:CartToken) { $cargs += @('-H', "Cart-Token: $script:CartToken") }
    if ($null -ne $Body) {
        # File body: PS 5.1 mangles quotes when passing JSON args to native exes
        $bodyFile = Join-Path $tmp 'body.json'
        [IO.File]::WriteAllText($bodyFile, ($Body | ConvertTo-Json -Depth 10 -Compress))
        $cargs += @('--data-binary', "@$bodyFile")
    }
    $raw = (& curl.exe @cargs "$Base$Path") -join "`n"
    $code = [int]($raw.Substring($raw.LastIndexOf("`n") + 1))
    $text = $raw.Substring(0, $raw.LastIndexOf("`n"))
    $m = Select-String -Path $hdr -Pattern '^Cart-Token:\s*(\S+)' | Select-Object -First 1
    if ($m) { $script:CartToken = $m.Matches[0].Groups[1].Value }
    $json = $null
    if ($text) { try { $json = $text | ConvertFrom-Json } catch { } }
    [pscustomobject]@{ Code = $code; Json = $json; Text = $text }
}

function Assert($cond, [string]$msg) {
    if (-not $cond) { Write-Host "FAIL  $msg" -ForegroundColor Red; exit 1 }
    Write-Host "ok    $msg" -ForegroundColor Green
}

function Get-Details([string]$Hash) {
    Invoke-Api GET "/wp-json/blockonomics-agentic-checkout/v1/orders/$Hash`?crypto=$Crypto"
}

# 0. Discovery: instructions route points agents at the Store API
$r = Invoke-Api GET '/wp-json/blockonomics-agentic-checkout/v1/agent'
Assert ($r.Code -eq 200 -and $r.Text -match 'wc/store/v1/cart') 'agent instructions served with Store API flow'

# 1. Discover product (agent browsing)
$r = Invoke-Api GET "/wp-json/wc/store/v1/products?slug=$ProductSlug"
Assert ($r.Code -eq 200 -and @($r.Json).Count -ge 1) "product '$ProductSlug' listed via Store API"
$productId = @($r.Json)[0].id

# 2. Cart: first GET issues Cart-Token, writes then skip nonce
$r = Invoke-Api GET '/wp-json/wc/store/v1/cart'
Assert ($script:CartToken) 'Cart-Token issued'
$r = Invoke-Api POST '/wp-json/wc/store/v1/cart/add-item' @{ id = $productId; quantity = 1 }
Assert ($r.Code -eq 201 -or $r.Code -eq 200) "add-item (HTTP $($r.Code))"

# 3. Checkout with crypto in payment_data
$checkout = @{
    billing_address = @{
        first_name = 'Agent'; last_name = 'Smoke'; address_1 = '1 Test St'; city = 'San Francisco'
        state = 'CA'; postcode = '94103'; country = 'US'; email = 'agent-smoke@example.com'; phone = ''
    }
    payment_method  = 'blockonomics'
    payment_data    = @(@{ key = 'crypto'; value = $Crypto })
}
$r = Invoke-Api POST '/wp-json/wc/store/v1/checkout' $checkout
if ($r.Code -ne 200) { Write-Host $r.Text }
Assert ($r.Code -eq 200) "checkout (HTTP $($r.Code))"
$pd = @{}
foreach ($kv in $r.Json.payment_result.payment_details) { $pd[$kv.key] = $kv.value }
Write-Host ($pd | ConvertTo-Json)
Assert ($r.Json.payment_result.payment_status -eq 'success') 'payment_result success'
Assert ($pd.address -and $pd.amount -and $pd.order_hash -and $pd.expires_at) 'payment_details has address/amount/order_hash/expires_at'
Assert ($pd.payment_status -eq 'awaiting_payment') 'initial payment_status=awaiting_payment'
$hash = $pd.order_hash

# 4. Details endpoint reuses unexpired quote
$r = Get-Details $hash
Assert ($r.Code -eq 200) 'GET order details'
Assert ($r.Json.address -eq $pd.address -and $r.Json.amount_base_units -eq $pd.amount_base_units) 'quote reused (same address + amount)'

# 5. Negative cases
$r = Invoke-Api GET "/wp-json/blockonomics-agentic-checkout/v1/orders/deadbeef`?crypto=$Crypto"
Assert ($r.Code -eq 404) "bogus hash -> 404 (got $($r.Code))"
$r = Invoke-Api GET "/wp-json/blockonomics-agentic-checkout/v1/orders/$hash`?crypto=doge"
Assert ($r.Code -eq 400) "unsupported crypto -> 400 (got $($r.Code))"

# 6. USDT: agent registers txhash
if ($Crypto -eq 'usdt' -and $TxHash) {
    $r = Invoke-Api POST "/wp-json/blockonomics-agentic-checkout/v1/orders/$hash/txhash" @{ txhash = $TxHash }
    Write-Host $r.Text
    Assert ($r.Code -eq 200) "txhash submit (HTTP $($r.Code))"
    $r = Invoke-Api POST "/wp-json/blockonomics-agentic-checkout/v1/orders/$hash/txhash" @{ txhash = ('0x' + ('f' * 64)) }
    Assert ($r.Code -eq 400) "different txhash rejected (got $($r.Code))"
}

# 7. Simulated Blockonomics callbacks (localhost unreachable from blockonomics.co)
if ($SimulateCallback) {
    # wp-env logs progress to stderr; npx.cmd avoids npx.ps1 turning that into terminating errors
    $secret = $Secret
    if (-not $secret) {
        $ErrorActionPreference = 'Continue'
        $secret = & npx.cmd wp-env run cli wp option get blockonomics_callback_secret 2>$null |
            Where-Object { $_ -match '^[0-9a-f]{40}$' } | Select-Object -First 1
        $ErrorActionPreference = 'Stop'
    }
    Assert ($secret) 'callback secret available'
    $txid = if ($Crypto -eq 'usdt' -and $TxHash) { $TxHash } else { -join ((1..64) | ForEach-Object { '{0:x}' -f (Get-Random -Max 16) }) }
    $paid = [math]::Floor([double]$pd.amount_base_units * $PayRatio)
    $final = if ($PayRatio -lt 1) { 'underpaid' } else { 'confirmed' }
    foreach ($step in @(@{ s = 0; want = 'detected' }, @{ s = 2; want = $final })) {
        $q = "wc-api=WC_Gateway_Blockonomics&secret=$secret&crypto=$Crypto&addr=$($pd.address)&status=$($step.s)&value=$paid&txid=$txid"
        $null = & curl.exe -s "$Base/?$q"
        $r = Get-Details $hash
        Assert ($r.Json.payment_status -eq $step.want) "callback status=$($step.s) -> payment_status=$($r.Json.payment_status) (want $($step.want))"
    }
    Write-Host "order_status=$($r.Json.order_status)"
}

Remove-Item -Recurse -Force $tmp
Write-Host "`nDONE  order_hash=$hash" -ForegroundColor Cyan
