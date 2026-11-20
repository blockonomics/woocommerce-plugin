class CopyToClipboard {
  /* 
    JS to Handle Copy Functions from the correct structured data
    */

  copyToClipboard = async function (evt) {
    var copy_element = evt.currentTarget.parentElement;
    var copy_input = copy_element.querySelector('.output-copy');
    var copy_value = copy_element.querySelector('input')
      ? copy_input.querySelector('input').value
      : copy_input.querySelector('.copied-value').innerHTML;
    // Allow copying of code snippets such as js script tags
    var copy_html = this.decodeHtml(copy_value);

    var textarea = document.createElement('textarea');
    textarea.id = 'temp_element';
    textarea.style.height = 0;
    document.body.appendChild(textarea);
    textarea.value = copy_html;

    var selector = document.querySelector('#temp_element');
    selector.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    this.processOverlay(copy_element);
  };

  processOverlay = function (copy_element) {
    var copy_input = copy_element.querySelector('.output-copy');
    var copy_value = copy_input.querySelector('input')
      ? copy_input.querySelector('input')
      : copy_input.querySelector('.copied-value');
    // Check if the overlay is already displayed
    if (copy_value.style.display == 'none') {
      return;
    }
    const copied_overlay = this.createCopiedOverlay(copy_value, copy_element);
    // Show copied overlay
    this.showOverlay(copied_overlay);
    self = this;
    setTimeout(function () {
      // Hide copied overlay
      self.hideOverlay(copied_overlay);
    }, 2000);
  };

  createCopiedOverlay = function (copy_value, copy_element) {
    // Fetch existing css styles of the element
    const boxStyles = window.getComputedStyle(copy_value);
    var copied_overlay = copy_element.querySelector('.copied-overlay');
    // Apply blockonomics css to the overlay
    return this.addOverlayStyles(
      copied_overlay,
      boxStyles,
      copy_value
    );
  };

  addExistingStyles = function (boxStyles) {
    let cssText = boxStyles.cssText;
    if (!cssText) {
      cssText = Array.from(boxStyles).reduce((str, property) => {
        return `${str}${property}:${boxStyles.getPropertyValue(property)};`;
      }, '');
    }
    return cssText;
  };

  addOverlayStyles = function (copied_overlay, boxStyles, copy_value) {
    let target_position = copy_value.getBoundingClientRect();
    
    let border = {
      left: parseFloat(boxStyles.borderLeftWidth.replace('px', '')),
      right: parseFloat(boxStyles.borderRightWidth.replace('px', '')),
      top: parseFloat(boxStyles.borderTopWidth.replace('px', '')),
      bottom: parseFloat(boxStyles.borderBottomWidth.replace('px', '')),
    };

    copied_overlay.style.backgroundColor = boxStyles.backgroundColor;

    copied_overlay.style.width =
      target_position.width - border.left - border.right + 'px';
    copied_overlay.style.height =
      target_position.height - border.top - border.bottom + 'px';
    copied_overlay.style.left = border.left + 'px';
    copied_overlay.style.top = border.top + 'px';

    copied_overlay.style.borderTopLeftRadius = boxStyles.borderTopLeftRadius;
    copied_overlay.style.borderTopRightRadius = boxStyles.borderTopLeRightdius;
    copied_overlay.style.borderBottomLeftRadius = boxStyles.borderBottomLeftRadius;
    copied_overlay.style.borderBottomRightRadius = boxStyles.borderBottomRightRadius;

    copied_overlay.querySelector('img').style.height = '17px';
    return copied_overlay;
  }; 

  showOverlay =  async function (copied_overlay) {
    copied_overlay.style.display = "flex";
  };

  hideOverlay = function (copied_overlay) {
    copied_overlay.style.display = "none";
  };

  decodeHtml = function (html) {
    var txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  };

  /*
    JS for attachment to input, textarea, span, div elements
    Looks for elements with the data-copy attribute and wraps in the correct copy structure
    */
  processElement = function (elem) {
    // Check if already processed
    // Checks if value exists to avoid processing elements before ng-value has set the value
    if (
      elem.classList.contains('copied-value') ||
      (!elem.value && !elem.innerHTML)
    ) {
      return;
    }
    elem.classList.add('copied-value');
    // Check the color to use for icons
    const iconColor = window
      .getComputedStyle(elem)
      .getPropertyValue('color');

    // Wrap the element in the 1st div
    const containerInner = document.createElement('div');
    containerInner.classList.add('output-copy');
    this.wrapElement(elem, containerInner);

    // Create the Copied overlay
    const copied = document.createElement('span');
    copied.classList.add('copied-overlay');
    copied.innerHTML =
      'Copied <img class="blockonomics-icon" src="' +
      this.getCheckImage(iconColor) +
      '">';
    containerInner.appendChild(copied);

    // Wrap the element in the 2nd div
    const containerOuter = document.createElement('div');
    containerOuter.classList.add('output-copy-container');
    this.wrapElement(containerInner, containerOuter);

    // Create the copy icon
    const image = document.createElement('img');
    image.classList.add('blockonomics-icon');
    image.setAttribute('src', this.getCopyImage(iconColor));
    image.addEventListener('click', (evt) => this.copyToClipboard(evt));
    containerOuter.appendChild(image);
  };

  processElements = function () {
    // Process all elements with the data-copy attribute
    const elems = document.querySelectorAll('[data-copy]');
    for (var i = elems.length - 1; i >= 0; i--) {
      this.processElement(elems[i]);
    }
  };

  getCopyImage = function (iconColor) {
    return (
      'data:image/svg+xml;base64,' +
      btoa(`
        <svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z"
            fill="${iconColor}"/>
        </svg>
        `)
    );
  };

  getCheckImage = function (iconColor) {
    return (
      'data:image/svg+xml;base64,' +
      btoa(`
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4.04706 14C4.04706 8.55609 8.46025 4.1429 13.9042 4.1429C19.3482 4.1429 23.7613 8.55609 23.7613 14C23.7613 19.444 19.3482 23.8572 13.9042 23.8572C8.46025 23.8572 4.04706 19.444 4.04706 14Z" stroke="${iconColor}" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M9.52325 14L12.809 17.2858L18.2852 11.8096" stroke="${iconColor}" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        `)
    );
  };

  wrapElement = function (el, wrapper) {
    el.parentNode.insertBefore(wrapper, el);
    wrapper.appendChild(el);
  };
}
  
  /* 
  Include CSS in header using JS to simplyfy setup
  Styles are only required once JS has loaded
  */
  var copyComponentStyles = `
  .output-copy-container{
    height: 100%;
    display: flex;
    align-items: center;
    width: 100%;
  }
  .output-copy-container .output-copy {
    max-width: -webkit-calc(100% - 30px) !important;
    max-width:    -moz-calc(100% - 30px) !important;
    max-width:         calc(100% - 30px) !important;
    word-break: break-all;
    max-width: 100%;
    width: 100%;
    position: relative;
    display: flex;
  }
  .output-copy-container .copied-overlay {
    padding: 0;
    width: 100%;
    height: 0%;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 0;
    overflow: hidden;
    display: none;
    
  }
  .output-copy-container .blockonomics-icon {
    transition: transform .4s;
    height: 21px;
    vertical-align: text-bottom;
    padding-left: 5px;
  }
  .output-copy .blockonomics-icon{
    padding-left: 0px;
  }
  .output-copy-container .blockonomics-icon:hover {
    -ms-transform: scale(1.2);
    -webkit-transform: scale(1.2);
    transform: scale(1.2);
    cursor: pointer;
  }
  `;
  
  var head = document.head || document.getElementsByTagName('head')[0];
  var style = document.createElement('style');
  
  style.type = 'text/css';
  if (style.styleSheet){
    style.styleSheet.cssText = copyComponentStyles;
  } else {
    style.appendChild(document.createTextNode(copyComponentStyles));
  }
  head.appendChild(style);
  
  
  const copyToClipboard = new CopyToClipboard()
  copyToClipboard.processElements()
  
  // Fix for angularjs and other dynamically loaded pages/popups etc.
  // Watches for any DOM changes which include the data-copy attribute
  const blockonomicsObserver = new MutationObserver((mutations) => {
    mutations.forEach(element => {
      var found_elem = element.target.querySelector('[data-copy]')
      if(found_elem){
        copyToClipboard.processElement(found_elem)
      }
    })
  });
  blockonomicsObserver.observe(document, {
    subtree: true,
    attributes: true
  });