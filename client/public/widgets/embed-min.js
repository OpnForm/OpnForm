!(function (n,) {
  if (typeof document !== "undefined") {
    const e = document.head || document.getElementsByTagName("head")[0]
    const t = document.createElement("style");
    (t.type = "text/css"),
      e.appendChild(t),
      t.styleSheet
        ? (t.styleSheet.cssText = n)
        : t.appendChild(document.createTextNode(n))
  }
})(
  ".nf-main {\n      position: fixed;\n      width: 100%;\n      height: 100vh;\n      top: 0px;\n      bottom: 0px;\n      left: 0px;\n      right: 0px;\n      z-index: 500;\n      pointer-events: none;\n    }\n    .nf-main .nf-emoji {\n       pointer-events: auto;\n       width:60px;\n       height: 60px;\n       display: flex;\n       align-items:center;\n       justify-content:center;\n       text-align: center;\n       background-color: #3B82F6;\n       padding: 8px 10px;\n       border: none;\n       cursor: pointer;\n       position: fixed;\n       bottom: 23px;\n       right: 28px;\n       border-radius: 100%;\n       z-index: 999\n     }\n     .nf-main.nf-left .nf-emoji{\n        left: 28px !important;\n        right: inherit !important\n     }\n     .nf-main .nf-emoji .nf-emoji-icon, .nf-main .nf-emoji .nf-emoji-icon-close{\n        font-size: 30px;\n        color:white;\n      }\n    .nf-main .nf-emoji .nf-emoji-icon-close {\n       display: none;\n     }\n    .nf-main.open .nf-emoji .nf-emoji-icon{\n       display: none !important\n    }\n    .nf-main.open .nf-emoji .nf-emoji-icon-close{\n       display: block !important\n    }\n    .nf-main .nf-popup {\n\n       display: flex;\n       align-items: end;\n       flex-direction: column-reverse;\n       align-content: flex-end;\n       padding: 20px;\n       padding-bottom: 100px;\n       width: 100%;\n       height: 100vh;\n       visibility: hidden;\n       opacity:0; transition:\n       opacity 0.2s, 0.2s ease-in-out;\n       transform: translateY(30px);\n    }\n    .nf-main.open .nf-popup {\n      visibility: visible !important;\n      opacity: 1;\n      transform: translateY(0px);\n    }\n    .nf-main .nf-popup iframe {\n      width: 100%;\n      pointer-events: auto;\n      z-index: 999!important;\n      bottom: 100px;\n      right: 20px;\n      height: 450px;\n      background: #fff;\n      border-radius: 12px;\n      box-shadow: 0 6px 6px 0 rgba(0,0,0,.02),0 8px 24px 0 rgba(0,0,0,.12)!important\n    }\n    .nf-main.nf-left .nf-popup {\n      align-items: start !important;\n    }",
),
  (function () {
    const n = JSON.parse(document.currentScript.getAttribute("data-nf"))
    let e = n?.formurl || null
    if (window.location !== window.parent.location || window.frameElement || !e)
      return !1
    e = e + (e.indexOf("?") === -1 ? "?" : "&") + "popup=true"
    const t = n?.emoji || "💬"
    const i = n?.position === "left" ? "nf-left" : ""
    const o = n?.bgcolor || "#3B82F6"
    const a = n?.width || 500
    const r = document.body.querySelector(".nf-main")
    r && r.remove()
    const p = document.createElement("div");
    (p.className = `nf-main ${i}`)
    const d = document.createElement("div");
    (d.className = "nf-popup"), p.appendChild(d)
    let c = !1, l = null
    const s = document.createElement("div");
    (s.className = "nf-emoji"),
      (s.role = "button"),
      (s.style.backgroundColor = `${o}`),
      (s.innerHTML = `<span class='nf-emoji-icon'>${t}</span><span class='nf-emoji-icon-close'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-8 h-8'>\n  <path stroke-linecap='round' stroke-linejoin='round' d='M6 18L18 6M6 6l12 12' />\n</svg>\n</span>`),
      (s.onclick = () => {
        c || ((l = document.createElement("iframe")), (l.src = e), (l.title = "OpnForm"), (l.style.maxWidth = `${a}px`), d.appendChild(l), (c = !0)), p.classList.toggle("open")
      }),
      p.appendChild(s),
      document.body.appendChild(p)
  })()
