/* eslint-disable */
"use strict";(function(){(function(o){if(o&&typeof document<"u"){const n=document.head||document.getElementsByTagName("head")[0],e=document.createElement("style");e.type="text/css",n.appendChild(e),e.styleSheet?e.styleSheet.cssText=o:e.appendChild(document.createTextNode(o))}})(`.nf-main {
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
    }`)})(),(function(){const o=["utm_source","utm_medium","utm_campaign","utm_id","utm_term","utm_content","utm_source_platform","utm_creative_format","utm_marketing_tactic","gclid","gbraid","wbraid","dclid","fbclid","ttclid","msclkid"],n=JSON.parse(document.currentScript.getAttribute("data-nf"));let e=n?.formurl||null;if(window.location!==window.parent.location||window.frameElement||!e)return!1;const i=new URL(e,window.location.href),u=new URLSearchParams(window.location.search);o.forEach(m=>{const d=i.searchParams.getAll(m).find(s=>s.trim()!==""&&s.length<=2048);if(d!==void 0){i.searchParams.set(m,d);return}const f=u.getAll(m).find(s=>s.trim()!==""&&s.length<=2048);f!==void 0&&i.searchParams.set(m,f)}),i.searchParams.set("popup","true"),e=i.toString();const h=n?.emoji||"\u{1F4AC}",x=n?.position==="left"?"nf-left":"",g=n?.bgcolor||"#3B82F6",w=n?.width||500,c=document.body.querySelector(".nf-main");c&&c.remove();const a=document.createElement("div");a.className=`nf-main ${x}`;const l=document.createElement("div");l.className="nf-popup",a.appendChild(l);let p=!1,r=null;const t=document.createElement("div");t.className="nf-emoji",t.role="button",t.style.backgroundColor=`${g}`,t.innerHTML=`<span class='nf-emoji-icon'></span><span class='nf-emoji-icon-close'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-8 h-8'>
  <path stroke-linecap='round' stroke-linejoin='round' d='M6 18L18 6M6 6l12 12' />
</svg>
</span>`,t.querySelector(".nf-emoji-icon").textContent=h,t.onclick=()=>{p||(r=document.createElement("iframe"),r.src=e,r.title="OpnForm",r.style.maxWidth=`${w}px`,l.appendChild(r),p=!0),a.classList.toggle("open")},a.appendChild(t),document.body.appendChild(a)})();
