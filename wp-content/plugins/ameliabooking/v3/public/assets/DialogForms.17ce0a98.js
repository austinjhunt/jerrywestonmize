import{y as k,E as n,cx as z,p as c,B as v,D as B,s as C,a3 as R,k as m,a2 as V,e as p,w as W,b as i,a8 as M,ak as S,j as _,C as y,o as D,ac as $}from"./stepForm.02a44866.js";import{e as j}from"./catalogForm.e331ab21.js";import L from"./EventsListForm.a2f93d7f.js";import"./customizeForm.bf064c84.js";import"./customerPanel.40583e99.js";const N={name:"EventsListFormWrapper"},O=Object.assign(N,{setup(P){let e=_("shortcodeData"),h=k({sbsNew:n(z),cbf:n(j),elf:n(L)}),l=c(0);v("dialogWrapperWidth",l);let a=c(!1);v("formDialogVisibility",a);let u=c(!1),d=e.value.trigger_type&&e.value.trigger_type==="class"?[...document.getElementsByClassName(e.value.trigger)]:[document.getElementById(e.value.trigger)];function w(s){u.value=s}let f=c(100);d.forEach(s=>{s.addEventListener("click",r=>{r.preventDefault(),r.stopPropagation(),a.value=!0,setTimeout(()=>{window.dispatchEvent(new Event("resize"))},f.value)})}),B(u,s=>{s&&d.forEach(r=>{r.dispatchEvent(new Event("click"))})});function x(){a.value=!1}C(()=>{R("renderPopup",{resizeAfter:f})});const o=_("settings");let t=m(()=>o.customizedData&&e.value.triggered_form in o.customizedData?o.customizedData[e.value.triggered_form].colors:y[e.value.triggered_form].colors),b=m(()=>o.customizedData&&e.value.triggered_form in o.customizedData?o.customizedData[e.value.triggered_form]:y[e.value.triggered_form]),E=m(()=>({"--am-c-primary":t.value.colorPrimary,"--am-c-success":t.value.colorSuccess,"--am-c-error":t.value.colorError,"--am-c-warning":t.value.colorWarning,"--am-c-main-bgr":t.value.colorMainBgr,"--am-c-main-heading-text":t.value.colorMainHeadingText,"--am-c-main-text":t.value.colorMainText}));return V(()=>{e.value.triggered_form==="sbsNew"&&(l.value=b.value.sidebar.options.self.visibility?"760px":"520px"),e.value.triggered_form==="cbf"&&(l.value="1140px"),e.value.triggered_form==="elf"&&(l.value="792px")},{flush:"post"}),(s,r)=>(D(),p(S,{modelValue:i(a),"onUpdate:modelValue":r[0]||(r[0]=g=>M(a)?a.value=g:a=g),"append-to-body":!0,"modal-class":`amelia-v2-booking am-forms-dialog am-${i(e).triggered_form}`,"close-on-click-modal":!1,"close-on-press-escape":!1,"custom-styles":i(E),"used-for-shortcode":!0,width:i(l),onClosed:x},{default:W(()=>[(D(),p($(i(h)[i(e).triggered_form]),{onIsRestored:w}))]),_:1},8,["modelValue","modal-class","custom-styles","width"]))}});export{O as default};
