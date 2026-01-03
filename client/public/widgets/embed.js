/**
 * OpenForms Embed, allowing you to add a popup form to your website.
 * All changes to this file should be followed by an update of the minified version (embed-min.js)
 */
!(function () {
  !(function (e) {
    if (e && typeof document !== "undefined") {
      const head = document.head || document.getElementsByTagName("head")[0]
      const styleEl = document.createElement("style");
      (styleEl.type = "text/css"),
        head.appendChild(styleEl),
        styleEl.styleSheet
          ? (styleEl.styleSheet.cssText = e)
          : styleEl.appendChild(document.createTextNode(e))
    }
  })(`.nf-main {
      position: fixed;
      width: 100%;
      height: 100vh;
      top: 0px;
      bottom: 0px;
      left: 0px;
      right: 0px;
      z-index: 500;
      pointer-events: none;
    }
    .nf-main .nf-emoji {
       pointer-events: auto;
       width:60px;
       height: 60px;
       display: flex;
       align-items:center;
       justify-content:center;
       text-align: center;
       background-color: #3B82F6;
       padding: 8px 10px;
       border: none;
       cursor: pointer;
       position: fixed;
       bottom: 23px;
       right: 28px;
       border-radius: 100%;
       z-index: 999
     }
     .nf-main.nf-left .nf-emoji{
        left: 28px !important;
        right: inherit !important
     }
     .nf-main .nf-emoji .nf-emoji-icon, .nf-main .nf-emoji .nf-emoji-icon-close{
        font-size: 30px;
        color:white;
      }
    .nf-main .nf-emoji .nf-emoji-icon-close {
       display: none;
     }
    .nf-main.open .nf-emoji .nf-emoji-icon{
       display: none !important
    }
    .nf-main.open .nf-emoji .nf-emoji-icon-close{
       display: block !important
    }
    .nf-main .nf-popup {

       display: flex;
       align-items: end;
       flex-direction: column-reverse;
       align-content: flex-end;
       padding: 20px;
       padding-bottom: 100px;
       width: 100%;
       height: 100vh;
       visibility: hidden;
       opacity:0; transition:
       opacity 0.2s, 0.2s ease-in-out;
       transform: translateY(30px);
    }
    .nf-main.open .nf-popup {
      visibility: visible !important;
      opacity: 1;
      transform: translateY(0px);
    }
    .nf-main .nf-popup iframe {
      width: 100%;
      pointer-events: auto;
      z-index: 999!important;
      bottom: 100px;
      right: 20px;
      height: 450px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 6px 6px 0 rgba(0,0,0,.02),0 8px 24px 0 rgba(0,0,0,.12)!important
    }
    .nf-main.nf-left .nf-popup {
      align-items: start !important;
    }`)
})();

(function () {
  const nfData = JSON.parse(document.currentScript.getAttribute("data-nf"))
  let formUrl = nfData?.formurl || null
  if (
    window.location !== window.parent.location ||
    window.frameElement ||
    !formUrl
  ) {
    // Do nothing
    return false
  }

  // Add popup param to formUrl
  formUrl = formUrl + (formUrl.indexOf("?") === -1 ? "?" : "&") + "popup=true"

  // Settings
  const emoji = nfData?.emoji || "💬"
  const position = nfData?.position === "left" ? "nf-left" : ""
  const emojiBgColor = nfData?.bgcolor || "#3B82F6"
  const width = nfData?.width || 500

  // Remove old popup, if there
  const oldEl = document.body.querySelector(".nf-main")
  if (oldEl) {
    oldEl.remove()
  }

  // Iframe popup
  const mainDiv = document.createElement("div")
  mainDiv.className = `nf-main ${position}`
  const popupDiv = document.createElement("div")
  popupDiv.className = "nf-popup"
  mainDiv.appendChild(popupDiv)
  
  let iframeCreated = false
  let iframe = null

  // Emoji button
  const emojiButton = document.createElement("div")
  emojiButton.className = "nf-emoji"
  emojiButton.role = "button"
  emojiButton.style.backgroundColor = `${emojiBgColor}`
  emojiButton.innerHTML = `<span class='nf-emoji-icon'>${emoji}</span><span class='nf-emoji-icon-close'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-8 h-8'>
  <path stroke-linecap='round' stroke-linejoin='round' d='M6 18L18 6M6 6l12 12' />
</svg>
</span>`
  emojiButton.onclick = () => {
    // Create iframe only on first click
    if (!iframeCreated) {
      iframe = document.createElement("iframe")
      iframe.src = formUrl
      iframe.title = "OpnForm"
      iframe.style.maxWidth = `${width}px`
      popupDiv.appendChild(iframe)
      iframeCreated = true
    }
    mainDiv.classList.toggle("open")
  }
  mainDiv.appendChild(emojiButton)

  // Append to the body
  document.body.appendChild(mainDiv)
})()
