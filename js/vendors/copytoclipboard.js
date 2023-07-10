class CopyToClipboard {
    /* 
    JS to Handle Copy Functions from the correct structured data
    */
  
    copyToClipboard = async function(evt) {
      var copy_element = evt.currentTarget.parentElement;
      var copy_input = copy_element.querySelector(".output-copy");
      var copy_value = copy_element.querySelector("input")
        ? copy_input.querySelector("input").value
        : copy_input.querySelector(".copied-value").innerHTML;
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
    }
  
    processOverlay = function (copy_element) {
      var copy_input = copy_element.querySelector(".output-copy");
      var copy_value = copy_input.querySelector("input")
        ? copy_input.querySelector("input")
        : copy_input.querySelector(".copied-value");
      // Check if the overlay is already displayed
      if(copy_value.style.display == 'none'){
        return
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
  
    createCopiedOverlay = function(copy_value, copy_element) {
      // Fetch existing css styles of the element
      const boxStyles = window.getComputedStyle(copy_value);
      var copied_overlay = copy_element.querySelector(".copied-overlay");
      // Assign existing css styles to overlay
      copied_overlay.style.cssText = this.addExistingStyles(boxStyles);
      // Apply blockonomics css to the overlay
      copied_overlay = this.addOverlayStyles(copied_overlay, boxStyles, copy_value);
      return copied_overlay;
    }
  
    addExistingStyles = function(boxStyles) {
      let cssText = boxStyles.cssText;
      if (!cssText) {
        cssText = Array.from(boxStyles).reduce((str, property) => {
          return `${str}${property}:${boxStyles.getPropertyValue(property)};`;
        }, "");
      }
      return cssText;
    }
  
    addOverlayStyles = function(copied_overlay, boxStyles, copy_value) {
      copied_overlay.style.width =
        boxStyles.width != "auto"
          ? boxStyles.width
          : copy_value.getBoundingClientRect().width + "px";
      copied_overlay.style.height = boxStyles.height;
      copied_overlay.style.lineHeight = boxStyles.height;
      copied_overlay.style.textAlign = "center";
      copied_overlay.style.resize = "none";
      copied_overlay.querySelector("img").style.height = "17px";
      return copied_overlay;
    }
  
    showOverlay = function(copied_overlay, copy_value) {
      copied_overlay.style.display = "inline-block";
      copy_value.style.display = "none";
    }
  
    hideOverlay = function(copied_overlay, copy_value) {
      copied_overlay.style.display = "none";
      copy_value.style.display = "inline-block";
    }
  
    decodeHtml = function(html) {
      var txt = document.createElement("textarea");
      txt.innerHTML = html;
      return txt.value;
    }
    /*
    JS for attachment to input, textarea, span, div elements
    Looks for elements with the data-copy attribute and wraps in the correct copy structure
    */
    processElement = function(elem) {
      // Check if already processed
      // Checks if value exists to avoid processing elements before ng-value has set the value
      if(elem.classList.contains("copied-value")|| (!elem.value && !elem.innerHTML)){
        return
      }
      elem.classList.add("copied-value");
      // Check the color to use for icons
      const iconColor =
        elem.getAttribute("light-icons") === null ? "dark" : "light";
  
      // Wrap the element in the 1st div
      const containerInner = document.createElement("div");
      containerInner.classList.add("output-copy");
      this.wrapElement(elem, containerInner);
  
      // Create the Copied overlay
      const copied = document.createElement("span");
      copied.classList.add("copied-overlay");
      copied.innerHTML =
        'Copied <img class="blockonomics-icon" src="' +
        this.getCheckImage(iconColor) +
        '">';
      containerInner.appendChild(copied);
  
      // Wrap the element in the 2nd div
      const containerOuter = document.createElement("div");
      containerOuter.classList.add("output-copy-container");
      this.wrapElement(containerInner, containerOuter);
  
      // Create the copy icon
      const image = document.createElement("img");
      image.classList.add("blockonomics-icon");
      image.setAttribute("src", this.getCopyImage(iconColor));
      image.addEventListener('click', (evt) => this.copyToClipboard(evt))
      containerOuter.appendChild(image);
    }
  
    processElements = function() {
      // Process all elements with the data-copy attribute
      const elems = document.querySelectorAll("[data-copy]");
      for (var i = elems.length - 1; i >= 0; i--) {
        this.processElement(elems[i])
      }
    }
  
    getCopyImage = function(iconColor) {
      if (iconColor == "light")
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACoAAAAwCAYAAABnjuimAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAD3SURBVHgB7ZiBCcIwEEXP4gC6gSPYSR2hI+gGuoGO4AZ2g3iRNIRCsan/YgP/wdEE2ub1Qq5wIgTLJp045w566TSOGjsxZKPk3L8dBkHyLsaCS2mS8UlWKumJ6deMOinI4q3/9UXWNFIJFEWTJepLmMZV4+VsOYdyGZk89ePD9Ic622u0qvH0k5yMlq6zfq1umORktGidDfSqsf/4yITIN1GrOju1DssTGoqioSgaiqKhKBqKoqEoGoqioSiaakRj72ltvaYx3Ho0FEVDUTT11dEZ+DZg7OaVbprlZPQh5bkMg9l/o2oaueGBVpKvNMIL3iSRJBa8Aaooq4J9p7IAAAAAAElFTkSuQmCC";
      else
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACoAAAAwCAYAAABnjuimAAAACXBIWXMAABYlAAAWJQFJUiTwAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAADTSURBVHgB7ZhRCoMwEETH0gO0N8j9f6QnyBV6E3uDNiktBLGNMZPVwDxYUBD3aWA+BhDijQvjw0xhno2nStJCsFp0hJ1kseiQXFd95QaGkofPrBe15oROkCibUlEHm5wdP7sWycWHg23OTvghmxO1ztk4/rv8X47O48k6ZyOPMFegTrRVzi7uUTyxkSgbibKRKBuJspEoG4mykSgbibLpRjTtng7VNc3R0bORKBuJsukyR3PEwuqS3JuWZiV/9A57btiAw0GK3LWyrQvdKOhRIbkrL+nj620ZAt7+AAAAAElFTkSuQmCC";
    }
    
    getCheckImage = function(iconColor) {
      if (iconColor == "light")
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAABYlAAAWJQFJUiTwAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAKLSURBVHgB7VmLkdowEF1SgUtwB3EHRyfoKggdhA6ODqCDpANIBVwqcDrwdfAijcXEJ3ZlfRZfMuHN7Bhs7X9lr9dEDzzwf2NFigCwtofO0pM/Np4c3iz98vTT0nm1Wp3po2GNbix9tTQgH72lg6WWloY3/AV6OCzmiFX0BWURn0NvydA9gXjUBx9JY6mz1Ez4Gn/OXTsiHoAX0oY34BSJ3HZqcKJM43k5XHLlzSm7gI/4lirhnecyciINgC+bHoqbzskCn426cvJp5tLbkjK8E1ymy7IsREU18ok6XXnl7weMd5RQUEt3hndiqColL0QnlQXAuLFDpGeBiX5PC4MppV0Ns6GFwWRhSGVcFzHmG7jBWOu9+81cb3C7F9YpgncB04GU4Y2fDRLGtmOKd/vwkyD/c/D/BynCR/sYnH4Tlp+D/2tKUBA+TDpSAhP5K4ywvgvWzd9MmLprIsaINSysTzbe8zRIKLWQ6R0i6/pg6UbT+Fx7ShzgOsiNpvE59sQMk0rICIZtFI0vKqE+YOoia0UnUGm8lx9u4ksK07cchREnqowXZH+fXpeeA+F9/4kisPOdoz080zye/dochLpfZznAtBJI6ARnMmGoAChpJQTGpFZacMJQARhZ6R0xbvuhZGb8mTYMqOhicXszSe/JcHv7Ss6CBjAOz0K0OTKckH0g4CNfKfM7YvD9eI/lX+rLdYJ/N116rGKoBkwpqWcC8mBrTxqAPBfVGC1K0+75tiFDSSOk95oNUyBvC3m4e4LmcHeieA8ZqeP1A+LjdZ2yiThxfVBpQ2XaneqE23RH6MFlVr9kMhzpkQ8X8V2N4ff4zOrIjWVaT9PPrI5cO+za9de/4jPrAw/84/gNdmJbUKPGB0kAAAAASUVORK5CYII=";
      else
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAACXBIWXMAABYlAAAWJQFJUiTwAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAJiSURBVHgB7Zn/VcIwEMe/OkFHyAaygd2kcQK7gWwgG5QNdANgAnSCugFsoMkzfQ+TS8kl1/KH/bx3D5Fe8u39SEMAFhb+N3eQpTa2MvboXitnlrOxL2efxvbObo4V+GLsZOybab2xzpjCDbDCX8EXHbNZb+QZeRFPyYjGxIxF3d5U50QMPTBQuf/Zz7YYD8ArJsAK2CEeuRZ/BaegnS815jFjvFGOoCPeopwWdEZ2EIIqGxs5BTkU6GwUl5MGnV4FeRToTGdnWSGMinTkU+a05ZXVDx0xkML0KIQ9wS4lBcFUZtAS87Oy4Ee/x/z4pbRm+AbOGvPjZ+GU6ljnOjJp3Ni9+9unQtgLNRJYI9xoSdMgLUhbjPThfcTpwXt/gCyNE3bJOXLt3ntfIwH/YbKCHH7kr/XYChmLiV931YiYsRqmrueIh5ub3Y/+BDH8laoRFs/Vw3agdpCNsHiOnqiwWAlp0MIaQfFZJdQjvYk14jdRKh4Im/iY4vTGnFCDFloqnhr7/fLD2HPAX/cfMc7W2BOu84Rw/b+GP/cHEqgR1l3KTlBDLvIDWVsJyjF1K60hJ14j4yE2sC5w1u76E8p2sb2noeM4+8sXJwsSPBPzKzDZIOwFhelRCEuYFf0Baj/eY/4v9UVzUt9N5z5W0ShkQwwqnQkF+mBrAyF2oNd3icaOnXYnbRtSqUCnd8iGBg87Xov44e4Owoe7AxvEn7apx+sdxo/XxcomhkY8ciUmddqdhEJ4YlBiNuqTlMw1FH5vJCcjNuJrFAif4mdWa/ZYRjm7/JnVmt0OH9zrHgsLC0X8AGDcDy65U+3zAAAAAElFTkSuQmCC";
    }
    
    wrapElement = function(el, wrapper) {
      el.parentNode.insertBefore(wrapper, el);
      wrapper.appendChild(el);
    }
  
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