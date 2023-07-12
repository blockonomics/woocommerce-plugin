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

    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(copy_html);
    } else {
      var textarea = document.createElement('textarea');
      textarea.id = 'temp_element';
      textarea.style.height = 0;
      document.body.appendChild(textarea);
      textarea.value = copy_html;

      var selector = document.querySelector('#temp_element');
      selector.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }

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
    var copied_overlay = this.createCopiedOverlay(copy_value, copy_element);
    // Show copied overlay
    this.showOverlay(copied_overlay, copy_value);
    self = this;
    setTimeout(function () {
      // Hide copied overlay
      self.hideOverlay(copied_overlay, copy_value);
    }, 3000);
  };

  createCopiedOverlay = function (copy_value, copy_element) {
    // Fetch existing css styles of the element
    const boxStyles = window.getComputedStyle(copy_value);
    var copied_overlay = copy_element.querySelector('.copied-overlay');
    // Assign existing css styles to overlay
    copied_overlay.style.cssText = this.addExistingStyles(boxStyles);
    // Apply blockonomics css to the overlay
    copied_overlay = this.addOverlayStyles(
      copied_overlay,
      boxStyles,
      copy_value
    );
    return copied_overlay;
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
    copied_overlay.style.width =
      boxStyles.width != 'auto'
        ? boxStyles.width
        : copy_value.getBoundingClientRect().width + 'px';
    copied_overlay.style.height = boxStyles.height;
    copied_overlay.style.lineHeight = boxStyles.height;
    copied_overlay.style.textAlign = 'center';
    copied_overlay.style.resize = 'none';
    copied_overlay.querySelector('img').style.height = '17px';
    return copied_overlay;
  };

  showOverlay = function (copied_overlay, copy_value) {
    copied_overlay.style.display = 'inline-block';
    copy_value.style.display = 'none';
  };

  hideOverlay = function (copied_overlay, copy_value) {
    copied_overlay.style.display = 'none';
    copy_value.style.display = 'inline-block';
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
    const iconColor = window.getComputedStyle(elem.parentElement).getPropertyValue('color');

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
      <svg width="22" height="22" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
      <g id="icomoon-ignore">
      </g>
      <path d="M16 2.672c-7.361 0-13.328 5.967-13.328 13.328s5.968 13.328 13.328 13.328c7.361 0 13.328-5.967 13.328-13.328s-5.967-13.328-13.328-13.328zM16 28.262c-6.761 0-12.262-5.501-12.262-12.262s5.5-12.262 12.262-12.262c6.761 0 12.262 5.501 12.262 12.262s-5.5 12.262-12.262 12.262z" fill="${iconColor}">
      
      </path>
      <path d="M22.667 11.241l-8.559 8.299-2.998-2.998c-0.312-0.312-0.818-0.312-1.131 0s-0.312 0.818 0 1.131l3.555 3.555c0.156 0.156 0.361 0.234 0.565 0.234 0.2 0 0.401-0.075 0.556-0.225l9.124-8.848c0.317-0.308 0.325-0.814 0.018-1.131-0.309-0.318-0.814-0.325-1.131-0.018z" fill="${iconColor}">
      
      </path>
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
  }
  .output-copy-container .output-copy {
    max-width: -webkit-calc(100% - 30px) !important;
    max-width:    -moz-calc(100% - 30px) !important;
    max-width:         calc(100% - 30px) !important;
    word-break: break-all;
  }
  .output-copy-container .value {
    display: inline-block;
  }
  .output-copy-container .copied-overlay {
    width: 100%;
    text-align: center;
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
  const observer = new MutationObserver((mutations, observer) => {
    mutations.forEach(element => {
      var found_elem = element.target.querySelector('[data-copy]')
      if(found_elem){
        copyToClipboard.processElement(found_elem)
      }
    })
  });
  observer.observe(document, {
    subtree: true,
    attributes: true
  });