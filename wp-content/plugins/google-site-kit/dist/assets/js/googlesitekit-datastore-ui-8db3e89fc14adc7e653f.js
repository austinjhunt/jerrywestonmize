(window.__googlesitekit_webpackJsonp=window.__googlesitekit_webpackJsonp||[]).push([[12],{100:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return l}));var n=e(6),a=e.n(n),i=e(13),o=e(101),u=e(102);function c(t,r){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);r&&(n=n.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),e.push.apply(e,n)}return e}function s(t){for(var r=1;r<arguments.length;r++){var e=null!=arguments[r]?arguments[r]:{};r%2?c(Object(e),!0).forEach((function(r){a()(t,r,e[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):c(Object(e)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(e,r))}))}return t}var f={activeModules:[],isAuthenticated:!1,referenceSiteURL:"",trackingEnabled:!1,trackingID:"",userIDHash:"",userRoles:[]};function l(r){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:t,n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:t,a=s(s({},f),r);a.referenceSiteURL&&(a.referenceSiteURL=a.referenceSiteURL.toString().replace(/\/+$/,""));var c=Object(o.a)(a,e),l=Object(u.a)(a,e,c,n),d={},g=function(){for(var t=arguments.length,r=new Array(t),e=0;e<t;e++)r[e]=arguments[e];var n=JSON.stringify(r);d[n]||(d[n]=Object(i.once)(l)),d[n].apply(d,r)};return{enableTracking:function(){a.trackingEnabled=!0},disableTracking:function(){a.trackingEnabled=!1},initializeSnippet:c,isTrackingEnabled:function(){return!!a.trackingEnabled},trackEvent:l,trackEventOnce:g}}}).call(this,e(24))},101:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return o}));var n=e(60),a=e(35),i=e(51);function o(r,e){var o,u=Object(n.a)(e),c=r.activeModules,s=r.referenceSiteURL,f=r.userIDHash,l=r.userRoles,d=void 0===l?[]:l,g=r.isAuthenticated,p=r.pluginVersion;return function(){var e=t.document;if(void 0===o&&(o=!!e.querySelector("script[".concat(a.b,"]"))),!o){o=!0;var n=(null==d?void 0:d.length)?d.join(","):"";u("js",new Date),u("config",r.trackingID,{groups:"site_kit",send_page_view:r.isSiteKitScreen,dimension1:s,dimension2:n,dimension3:f,dimension4:p||"",dimension5:Array.from(i.a).join(","),dimension6:c.join(","),dimension7:g?"1":"0"}),u("config",r.trackingID_GA4,{groups:"site_kit",send_page_view:r.isSiteKitScreen,domain:s,plugin_version:p||"",enabled_features:Array.from(i.a).join(","),active_modules:c.join(","),authenticated:g?"1":"0",user_properties:{user_roles:n,user_identifier:f}});var l=e.createElement("script");return l.setAttribute(a.b,""),l.async=!0,l.src="https://www.googletagmanager.com/gtag/js?id=".concat(r.trackingID_GA4,"&l=").concat(a.a),e.head.appendChild(l),{scriptTagSrc:"https://www.googletagmanager.com/gtag/js?id=".concat(r.trackingID_GA4,"&l=").concat(a.a)}}}}}).call(this,e(24))},102:function(t,r,e){"use strict";e.d(r,"a",(function(){return d}));var n=e(5),a=e.n(n),i=e(6),o=e.n(i),u=e(17),c=e.n(u),s=e(60);function f(t,r){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);r&&(n=n.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),e.push.apply(e,n)}return e}function l(t){for(var r=1;r<arguments.length;r++){var e=null!=arguments[r]?arguments[r]:{};r%2?f(Object(e),!0).forEach((function(r){o()(t,r,e[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):f(Object(e)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(e,r))}))}return t}function d(t,r,e,n){var i=Object(s.a)(r);return function(){var r=c()(a.a.mark((function r(o,u,c,s){var f;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:if(t.trackingEnabled){r.next=3;break}return r.abrupt("return");case 3:return e(),f={send_to:"site_kit",event_category:o,event_label:c,value:s},r.abrupt("return",new Promise((function(t){var r,e,a=setTimeout((function(){n.console.warn('Tracking event "'.concat(u,'" (category "').concat(o,'") took too long to fire.')),t()}),1e3),c=function(){clearTimeout(a),t()};i("event",u,l(l({},f),{},{event_callback:c})),(null===(r=n._gaUserPrefs)||void 0===r||null===(e=r.ioo)||void 0===e?void 0:e.call(r))&&c()})));case 6:case"end":return r.stop()}}),r)})));return function(t,e,n,a){return r.apply(this,arguments)}}()}},1034:function(t,r,e){"use strict";e.r(r);var n=e(4),a=e.n(n),i=e(65),o=e(113),u=e(6),c=e.n(u),s=e(5),f=e.n(s),l=e(11),d=e.n(l),g=e(13),p=e(26),v=e(7);function b(t,r){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);r&&(n=n.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),e.push.apply(e,n)}return e}function m(t){for(var r=1;r<arguments.length;r++){var e=null!=arguments[r]?arguments[r]:{};r%2?b(Object(e),!0).forEach((function(r){c()(t,r,e[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):b(Object(e)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(e,r))}))}return t}var h={resetInViewHook:f.a.mark((function t(){var r,e;return f.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,a.a.commonActions.getRegistry();case 2:return r=t.sent,e=r.select(p.b).getValue("useInViewResetCount"),t.next=6,h.setValue("useInViewResetCount",e+1);case 6:return t.abrupt("return",t.sent);case 7:case"end":return t.stop()}}),t)})),setIsOnline:function(t){return d()(Object(g.isBoolean)(t),"value must be boolean."),h.setValue("isOnline",t)},setOverlayNotificationToShow:f.a.mark((function t(r){var e;return f.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return d()(r,"overlayNotification is required."),t.next=3,a.a.commonActions.getRegistry();case 3:if(e=t.sent,!e.select(p.b).getValue("activeOverlayNotification")){t.next=7;break}return t.abrupt("return");case 7:return t.next=9,h.setValue("activeOverlayNotification",r);case 9:return t.abrupt("return",t.sent);case 10:case"end":return t.stop()}}),t)})),dismissOverlayNotification:f.a.mark((function t(r){var e,n;return f.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return d()(r,"overlayNotification is required."),t.next=3,a.a.commonActions.getRegistry();case 3:return e=t.sent,n=e.select(p.b).getValue("activeOverlayNotification"),t.next=7,a.a.commonActions.await(e.dispatch(v.a).dismissItem(r));case 7:if(!n||r!==n){t.next=11;break}return t.next=10,h.setValues({activeOverlayNotification:void 0});case 10:return t.abrupt("return",t.sent);case 11:case"end":return t.stop()}}),t)})),setValues:function(t){return d()(Object(g.isPlainObject)(t),"values must be an object."),{payload:{values:t},type:"SET_VALUES"}},setValue:function(t,r){return d()(t,"key is required."),{payload:{key:t,value:r},type:"SET_VALUE"}}},y={initialState:{useInViewResetCount:0,isOnline:!0},actions:h,controls:{},reducer:function(t,r){var e=r.type,n=r.payload;switch(e){case"SET_VALUES":var a=n.values;return m(m({},t),a);case"SET_VALUE":var i=n.key,o=n.value;return m(m({},t),{},c()({},i,o));default:return t}},resolvers:{},selectors:{getValue:function(t,r){return t[r]},getInViewResetCount:function(t){return t.useInViewResetCount},getIsOnline:function(t){return t.isOnline},isShowingOverlayNotification:function(t,r){return t.activeOverlayNotification===r}}},O=a.a.combineStores(a.a.commonStore,y,Object(o.a)(p.b),Object(i.b)(p.b));O.initialState,O.actions,O.controls,O.reducer,O.resolvers,O.selectors;a.a.registerStore(p.b,O)},113:function(t,r,e){"use strict";e.d(r,"a",(function(){return b})),e.d(r,"c",(function(){return h})),e.d(r,"b",(function(){return y}));var n=e(22),a=e.n(n),i=e(6),o=e.n(i),u=e(5),c=e.n(u),s=e(11),f=e.n(s),l=e(4),d=e.n(l),g=e(39),p=e(8),v=d.a.createRegistryControl,b=function(t){var r;f()(t,"storeName is required to create a snapshot store.");var e={},n={deleteSnapshot:c.a.mark((function t(){var r;return c.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,{payload:{},type:"DELETE_SNAPSHOT"};case 2:return r=t.sent,t.abrupt("return",r);case 4:case"end":return t.stop()}}),t)})),restoreSnapshot:c.a.mark((function t(){var r,e,n,a,i,o,u=arguments;return c.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return r=u.length>0&&void 0!==u[0]?u[0]:{},e=r.clearAfterRestore,n=void 0===e||e,t.next=4,{payload:{},type:"RESTORE_SNAPSHOT"};case 4:if(a=t.sent,i=a.cacheHit,o=a.value,!i){t.next=13;break}return t.next=10,{payload:{snapshot:o},type:"SET_STATE_FROM_SNAPSHOT"};case 10:if(!n){t.next=13;break}return t.next=13,{payload:{},type:"DELETE_SNAPSHOT"};case 13:return t.abrupt("return",i);case 14:case"end":return t.stop()}}),t)})),createSnapshot:c.a.mark((function t(){var r;return c.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,{payload:{},type:"CREATE_SNAPSHOT"};case 2:return r=t.sent,t.abrupt("return",r);case 4:case"end":return t.stop()}}),t)}))},i=(r={},o()(r,"DELETE_SNAPSHOT",(function(){return Object(g.c)("datastore::cache::".concat(t))})),o()(r,"CREATE_SNAPSHOT",v((function(r){return function(){return Object(g.f)("datastore::cache::".concat(t),r.stores[t].store.getState())}}))),o()(r,"RESTORE_SNAPSHOT",(function(){return Object(g.d)("datastore::cache::".concat(t),p.b)})),r);return{initialState:e,actions:n,controls:i,reducer:function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:e,r=arguments.length>1?arguments[1]:void 0,n=r.type,i=r.payload;switch(n){case"SET_STATE_FROM_SNAPSHOT":var o=i.snapshot,u=(o.error,a()(o,["error"]));return u;default:return t}}}},m=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:d.a;return Object.values(t.stores).filter((function(t){return Object.keys(t.getActions()).includes("restoreSnapshot")}))},h=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:d.a;return Promise.all(m(t).map((function(t){return t.getActions().createSnapshot()})))},y=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:d.a;return Promise.all(m(t).map((function(t){return t.getActions().restoreSnapshot()})))}},2:function(t,r){t.exports=googlesitekit.i18n},26:function(t,r,e){"use strict";e.d(r,"b",(function(){return n})),e.d(r,"a",(function(){return a}));var n="core/ui",a="activeContextID"},32:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return O})),e.d(r,"b",(function(){return h})),e.d(r,"c",(function(){return y}));var n=e(100),a=t._googlesitekitTrackingData||{},i=a.activeModules,o=void 0===i?[]:i,u=a.isSiteKitScreen,c=a.trackingEnabled,s=a.trackingID,f=a.referenceSiteURL,l=a.userIDHash,d=a.isAuthenticated,g={activeModules:o,trackingEnabled:c,trackingID:s,trackingID_GA4:"G-EQDN3BWDSD",referenceSiteURL:f,userIDHash:l,isSiteKitScreen:u,userRoles:a.userRoles,isAuthenticated:d,pluginVersion:"1.125.0"},p=Object(n.a)(g),v=p.enableTracking,b=p.disableTracking,m=(p.isTrackingEnabled,p.initializeSnippet),h=p.trackEvent,y=p.trackEventOnce;function O(t){t?v():b()}u&&c&&m()}).call(this,e(24))},35:function(t,r,e){"use strict";e.d(r,"a",(function(){return n})),e.d(r,"b",(function(){return a}));var n="_googlesitekitDataLayer",a="data-googlesitekit-gtag"},39:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return l})),e.d(r,"d",(function(){return h})),e.d(r,"f",(function(){return y})),e.d(r,"c",(function(){return O})),e.d(r,"e",(function(){return w})),e.d(r,"b",(function(){return k}));var n=e(5),a=e.n(n),i=e(17),o=e.n(i),u=(e(29),e(8));function c(t,r){var e="undefined"!=typeof Symbol&&t[Symbol.iterator]||t["@@iterator"];if(!e){if(Array.isArray(t)||(e=function(t,r){if(!t)return;if("string"==typeof t)return s(t,r);var e=Object.prototype.toString.call(t).slice(8,-1);"Object"===e&&t.constructor&&(e=t.constructor.name);if("Map"===e||"Set"===e)return Array.from(t);if("Arguments"===e||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(e))return s(t,r)}(t))||r&&t&&"number"==typeof t.length){e&&(t=e);var n=0,a=function(){};return{s:a,n:function(){return n>=t.length?{done:!0}:{done:!1,value:t[n++]}},e:function(t){throw t},f:a}}throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}var i,o=!0,u=!1;return{s:function(){e=e.call(t)},n:function(){var t=e.next();return o=t.done,t},e:function(t){u=!0,i=t},f:function(){try{o||null==e.return||e.return()}finally{if(u)throw i}}}}function s(t,r){(null==r||r>t.length)&&(r=t.length);for(var e=0,n=new Array(r);e<r;e++)n[e]=t[e];return n}var f,l="googlesitekit_",d="".concat(l).concat("1.125.0","_").concat(t._googlesitekitBaseData.storagePrefix,"_"),g=["sessionStorage","localStorage"],p=[].concat(g),v=function(){var r=o()(a.a.mark((function r(e){var n,i;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:if(n=t[e]){r.next=3;break}return r.abrupt("return",!1);case 3:return r.prev=3,i="__storage_test__",n.setItem(i,i),n.removeItem(i),r.abrupt("return",!0);case 10:return r.prev=10,r.t0=r.catch(3),r.abrupt("return",r.t0 instanceof DOMException&&(22===r.t0.code||1014===r.t0.code||"QuotaExceededError"===r.t0.name||"NS_ERROR_DOM_QUOTA_REACHED"===r.t0.name)&&0!==n.length);case 13:case"end":return r.stop()}}),r,null,[[3,10]])})));return function(t){return r.apply(this,arguments)}}();function b(){return m.apply(this,arguments)}function m(){return(m=o()(a.a.mark((function r(){var e,n,i;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:if(void 0===f){r.next=2;break}return r.abrupt("return",f);case 2:e=c(p),r.prev=3,e.s();case 5:if((n=e.n()).done){r.next=15;break}if(i=n.value,!f){r.next=9;break}return r.abrupt("continue",13);case 9:return r.next=11,v(i);case 11:if(!r.sent){r.next=13;break}f=t[i];case 13:r.next=5;break;case 15:r.next=20;break;case 17:r.prev=17,r.t0=r.catch(3),e.e(r.t0);case 20:return r.prev=20,e.f(),r.finish(20);case 23:return void 0===f&&(f=null),r.abrupt("return",f);case 25:case"end":return r.stop()}}),r,null,[[3,17,20,23]])})))).apply(this,arguments)}var h=function(){var t=o()(a.a.mark((function t(r){var e,n,i,o,u,c,s;return a.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,b();case 2:if(!(e=t.sent)){t.next=10;break}if(!(n=e.getItem("".concat(d).concat(r)))){t.next=10;break}if(i=JSON.parse(n),o=i.timestamp,u=i.ttl,c=i.value,s=i.isError,!o||u&&!(Math.round(Date.now()/1e3)-o<u)){t.next=10;break}return t.abrupt("return",{cacheHit:!0,value:c,isError:s});case 10:return t.abrupt("return",{cacheHit:!1,value:void 0});case 11:case"end":return t.stop()}}),t)})));return function(r){return t.apply(this,arguments)}}(),y=function(){var r=o()(a.a.mark((function r(e,n){var i,o,c,s,f,l,g,p,v=arguments;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:return i=v.length>2&&void 0!==v[2]?v[2]:{},o=i.ttl,c=void 0===o?u.b:o,s=i.timestamp,f=void 0===s?Math.round(Date.now()/1e3):s,l=i.isError,g=void 0!==l&&l,r.next=3,b();case 3:if(!(p=r.sent)){r.next=14;break}return r.prev=5,p.setItem("".concat(d).concat(e),JSON.stringify({timestamp:f,ttl:c,value:n,isError:g})),r.abrupt("return",!0);case 10:return r.prev=10,r.t0=r.catch(5),t.console.warn("Encountered an unexpected storage error:",r.t0),r.abrupt("return",!1);case 14:return r.abrupt("return",!1);case 15:case"end":return r.stop()}}),r,null,[[5,10]])})));return function(t,e){return r.apply(this,arguments)}}(),O=function(){var r=o()(a.a.mark((function r(e){var n,i;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:return r.next=2,b();case 2:if(!(n=r.sent)){r.next=14;break}return r.prev=4,i=e.startsWith(l)?e:"".concat(d).concat(e),n.removeItem(i),r.abrupt("return",!0);case 10:return r.prev=10,r.t0=r.catch(4),t.console.warn("Encountered an unexpected storage error:",r.t0),r.abrupt("return",!1);case 14:return r.abrupt("return",!1);case 15:case"end":return r.stop()}}),r,null,[[4,10]])})));return function(t){return r.apply(this,arguments)}}(),w=function(){var r=o()(a.a.mark((function r(){var e,n,i,o;return a.a.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:return r.next=2,b();case 2:if(!(e=r.sent)){r.next=14;break}for(r.prev=4,n=[],i=0;i<e.length;i++)0===(o=e.key(i)).indexOf(l)&&n.push(o);return r.abrupt("return",n);case 10:return r.prev=10,r.t0=r.catch(4),t.console.warn("Encountered an unexpected storage error:",r.t0),r.abrupt("return",[]);case 14:return r.abrupt("return",[]);case 15:case"end":return r.stop()}}),r,null,[[4,10]])})));return function(){return r.apply(this,arguments)}}(),k=function(){var t=o()(a.a.mark((function t(){var r,e,n,i;return a.a.wrap((function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,b();case 2:if(!t.sent){t.next=25;break}return t.next=6,w();case 6:r=t.sent,e=c(r),t.prev=8,e.s();case 10:if((n=e.n()).done){t.next=16;break}return i=n.value,t.next=14,O(i);case 14:t.next=10;break;case 16:t.next=21;break;case 18:t.prev=18,t.t0=t.catch(8),e.e(t.t0);case 21:return t.prev=21,e.f(),t.finish(21);case 24:return t.abrupt("return",!0);case 25:return t.abrupt("return",!1);case 26:case"end":return t.stop()}}),t,null,[[8,18,21,24]])})));return function(){return t.apply(this,arguments)}}()}).call(this,e(24))},4:function(t,r){t.exports=googlesitekit.data},43:function(t,r,e){"use strict";e.d(r,"c",(function(){return n})),e.d(r,"e",(function(){return a})),e.d(r,"d",(function(){return i})),e.d(r,"b",(function(){return o})),e.d(r,"a",(function(){return u})),e.d(r,"f",(function(){return c}));var n="Date param must construct to a valid date instance or be a valid date instance itself.",a="Invalid dateString parameter, it must be a string.",i='Invalid date range, it must be a string with the format "last-x-days".',o=3600,u=86400,c=604800},44:function(t,r,e){"use strict";e.d(r,"a",(function(){return n}));var n=function(t){return t instanceof Date&&!isNaN(t)}},51:function(t,r,e){"use strict";(function(t){var n,a;e.d(r,"a",(function(){return i})),e.d(r,"b",(function(){return o}));var i=new Set((null===(n=t)||void 0===n||null===(a=n._googlesitekitBaseData)||void 0===a?void 0:a.enabledFeatures)||[]),o=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:i;return r instanceof Set&&r.has(t)}}).call(this,e(24))},54:function(t,r,e){"use strict";e.d(r,"a",(function(){return s}));var n=e(16),a=e.n(n),i=e(11),o=e.n(i),u=e(43),c=e(55),s=function(t){o()(Object(c.a)(t),u.e);var r=t.split("-"),e=a()(r,3),n=e[0],i=e[1],s=e[2];return new Date(n,i-1,s)}},55:function(t,r,e){"use strict";e.d(r,"a",(function(){return a}));var n=e(44),a=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"",r="string"==typeof t;if(!r)return!1;var e=t.split("-");return 3===e.length&&Object(n.a)(new Date(t))}},60:function(t,r,e){"use strict";e.d(r,"a",(function(){return a}));var n=e(35);function a(t){return function(){t[n.a]=t[n.a]||[],t[n.a].push(arguments)}}},61:function(t,r,e){"use strict";e.d(r,"d",(function(){return n.e})),e.d(r,"c",(function(){return n.d})),e.d(r,"b",(function(){return n.b})),e.d(r,"a",(function(){return n.a})),e.d(r,"e",(function(){return n.f})),e.d(r,"g",(function(){return u})),e.d(r,"h",(function(){return s})),e.d(r,"i",(function(){return f})),e.d(r,"j",(function(){return l.a})),e.d(r,"f",(function(){return g})),e.d(r,"k",(function(){return c.a}));var n=e(43),a=e(11),i=e.n(a),o=e(44),u=function(t){i()(Object(o.a)(t),n.c);var r="".concat(t.getMonth()+1),e="".concat(t.getDate());return[t.getFullYear(),r.length<2?"0".concat(r):r,e.length<2?"0".concat(e):e].join("-")},c=e(54),s=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"",r=arguments.length>1?arguments[1]:void 0,e=Object(c.a)(t);return e.setDate(e.getDate()-r),u(e)},f=function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"",r=t.split("-");return 3===r.length&&"last"===r[0]&&!Number.isNaN(r[1])&&!Number.isNaN(parseFloat(r[1]))&&"days"===r[2]},l=e(55);var d=e(2);function g(){var t=function(t){return Object(d.sprintf)(
/* translators: %s: number of days */
Object(d._n)("Last %s day","Last %s days",t,"google-site-kit"),t)};return{"last-7-days":{slug:"last-7-days",label:t(7),days:7},"last-14-days":{slug:"last-14-days",label:t(14),days:14},"last-28-days":{slug:"last-28-days",label:t(28),days:28},"last-90-days":{slug:"last-90-days",label:t(90),days:90}}}},65:function(t,r,e){"use strict";e.d(r,"a",(function(){return b})),e.d(r,"b",(function(){return m}));var n=e(6),a=e.n(n),i=e(30),o=e.n(i),u=e(144),c=e(11),s=e.n(c),f=e(88),l=e.n(f),d=e(8);function g(t,r){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);r&&(n=n.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),e.push.apply(e,n)}return e}function p(t){for(var r=1;r<arguments.length;r++){var e=null!=arguments[r]?arguments[r]:{};r%2?g(Object(e),!0).forEach((function(r){a()(t,r,e[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):g(Object(e)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(e,r))}))}return t}function v(t,r){if(r&&Array.isArray(r)){var e=r.map((function(t){return"object"===o()(t)?Object(d.A)(t):t}));return"".concat(t,"::").concat(l()(JSON.stringify(e)))}return t}var b={receiveError:function(t,r){var e=arguments.length>2&&void 0!==arguments[2]?arguments[2]:[];return s()(t,"error is required."),s()(r,"baseName is required."),s()(e&&Array.isArray(e),"args must be an array."),{type:"RECEIVE_ERROR",payload:{error:t,baseName:r,args:e}}},clearError:function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:[];return s()(t,"baseName is required."),s()(r&&Array.isArray(r),"args must be an array."),{type:"CLEAR_ERROR",payload:{baseName:t,args:r}}},clearErrors:function(t){return{type:"CLEAR_ERRORS",payload:{baseName:t}}}};function m(t){s()(t,"storeName must be defined.");var r={getErrorForSelector:function(t,e){var n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:[];return s()(e,"selectorName is required."),r.getError(t,e,n)},getErrorForAction:function(t,e){var n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:[];return s()(e,"actionName is required."),r.getError(t,e,n)},getError:function(t,r,e){var n=t.errors;return s()(r,"baseName is required."),n[v(r,e)]},getErrors:function(t){var r=new Set(Object.values(t.errors));return Array.from(r)},getMetaDataForError:function(t,r){var e=Object.keys(t.errors).find((function(e){return t.errors[e]===r}));return e?{baseName:e.substring(0,e.indexOf("::")),args:t.errorArgs[e]}:null},getSelectorDataForError:Object(u.b)((function(r){return function(e,n){var a=r(t).getMetaDataForError(n);if(a){var i=a.baseName,o=a.args;if(!!r(t)[i])return{storeName:t,name:i,args:o}}return null}})),hasErrors:function(t){return r.getErrors(t).length>0}};return{initialState:{errors:{},errorArgs:{}},actions:b,controls:{},reducer:function(t,r){var e=r.type,n=r.payload;switch(e){case"RECEIVE_ERROR":var i=n.baseName,o=n.args,u=n.error,c=v(i,o);return p(p({},t),{},{errors:p(p({},t.errors||{}),{},a()({},c,u)),errorArgs:p(p({},t.errorArgs||{}),{},a()({},c,o))});case"CLEAR_ERROR":var s=n.baseName,f=n.args,l=p({},t),d=v(s,f);return l.errors=p({},t.errors||{}),l.errorArgs=p({},t.errorArgs||{}),delete l.errors[d],delete l.errorArgs[d],l;case"CLEAR_ERRORS":var g=n.baseName,b=p({},t);if(g)for(var m in b.errors=p({},t.errors||{}),b.errorArgs=p({},t.errorArgs||{}),b.errors)(m===g||m.startsWith("".concat(g,"::")))&&(delete b.errors[m],delete b.errorArgs[m]);else b.errors={},b.errorArgs={};return b;default:return t}},resolvers:{},selectors:r}}},7:function(t,r,e){"use strict";e.d(r,"a",(function(){return n})),e.d(r,"b",(function(){return a})),e.d(r,"e",(function(){return i})),e.d(r,"d",(function(){return o})),e.d(r,"c",(function(){return u})),e.d(r,"z",(function(){return c})),e.d(r,"E",(function(){return s})),e.d(r,"G",(function(){return f})),e.d(r,"C",(function(){return l})),e.d(r,"D",(function(){return d})),e.d(r,"B",(function(){return g})),e.d(r,"A",(function(){return p})),e.d(r,"F",(function(){return v})),e.d(r,"f",(function(){return b})),e.d(r,"g",(function(){return m})),e.d(r,"h",(function(){return h})),e.d(r,"j",(function(){return y})),e.d(r,"l",(function(){return O})),e.d(r,"m",(function(){return w})),e.d(r,"n",(function(){return k})),e.d(r,"o",(function(){return j})),e.d(r,"q",(function(){return _})),e.d(r,"r",(function(){return S})),e.d(r,"s",(function(){return E})),e.d(r,"t",(function(){return A})),e.d(r,"v",(function(){return N})),e.d(r,"k",(function(){return x})),e.d(r,"x",(function(){return D})),e.d(r,"u",(function(){return P})),e.d(r,"y",(function(){return R})),e.d(r,"w",(function(){return T})),e.d(r,"i",(function(){return I})),e.d(r,"p",(function(){return C})),e.d(r,"I",(function(){return L})),e.d(r,"H",(function(){return V}));var n="core/user",a="connected_url_mismatch",i="__global",o="temporary_persist_permission_error",u="adblocker_active",c="googlesitekit_authenticate",s="googlesitekit_setup",f="googlesitekit_view_dashboard",l="googlesitekit_manage_options",d="googlesitekit_read_shared_module_data",g="googlesitekit_manage_module_sharing_options",p="googlesitekit_delegate_module_sharing_management",v="googlesitekit_update_plugins",b="kmAnalyticsAdSenseTopEarningContent",m="kmAnalyticsEngagedTrafficSource",h="kmAnalyticsLeastEngagingPages",y="kmAnalyticsNewVisitors",O="kmAnalyticsPopularAuthors",w="kmAnalyticsPopularContent",k="kmAnalyticsPopularProducts",j="kmAnalyticsReturningVisitors",_="kmAnalyticsTopCities",S="kmAnalyticsTopConvertingTrafficSource",E="kmAnalyticsTopCountries",A="kmAnalyticsTopRecentTrendingPages",N="kmAnalyticsTopTrafficSource",x="kmAnalyticsPagesPerVisit",D="kmAnalyticsVisitLength",P="kmAnalyticsTopReturningVisitorPages",R="kmSearchConsolePopularKeywords",T="kmAnalyticsVisitsPerVisitor",I="kmAnalyticsMostEngagingPages",C="kmAnalyticsTopCategories",L=[b,m,h,y,O,w,k,j,C,_,S,E,A,N,x,D,P,T,I,C],V=[].concat(L,[R])},70:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return j})),e.d(r,"d",(function(){return _})),e.d(r,"e",(function(){return E})),e.d(r,"c",(function(){return A})),e.d(r,"b",(function(){return N}));var n=e(16),a=e.n(n),i=e(30),o=e.n(i),u=e(6),c=e.n(u),s=e(22),f=e.n(s),l=e(13),d=e(69),g=e.n(d),p=e(2);function v(t,r){var e=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);r&&(n=n.filter((function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable}))),e.push.apply(e,n)}return e}function b(t){for(var r=1;r<arguments.length;r++){var e=null!=arguments[r]?arguments[r]:{};r%2?v(Object(e),!0).forEach((function(r){c()(t,r,e[r])})):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(e)):v(Object(e)).forEach((function(r){Object.defineProperty(t,r,Object.getOwnPropertyDescriptor(e,r))}))}return t}var m=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},e=O(t,r),n=e.formatUnit,a=e.formatDecimal;try{return n()}catch(t){return a()}},h=function(t){var r=y(t),e=r.hours,n=r.minutes,a=r.seconds;return a=("0"+a).slice(-2),n=("0"+n).slice(-2),"00"===(e=("0"+e).slice(-2))?"".concat(n,":").concat(a):"".concat(e,":").concat(n,":").concat(a)},y=function(t){return t=parseInt(t,10),Number.isNaN(t)&&(t=0),{hours:Math.floor(t/60/60),minutes:Math.floor(t/60%60),seconds:Math.floor(t%60)}},O=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},e=y(t),n=e.hours,a=e.minutes,i=e.seconds;return{hours:n,minutes:a,seconds:i,formatUnit:function(){var e=r.unitDisplay,o=b(b({unitDisplay:void 0===e?"short":e},f()(r,["unitDisplay"])),{},{style:"unit"});return 0===t?E(i,b(b({},o),{},{unit:"second"})):Object(p.sprintf)(
/* translators: 1: formatted seconds, 2: formatted minutes, 3: formatted hours */
Object(p._x)("%3$s %2$s %1$s","duration of time: hh mm ss","google-site-kit"),i?E(i,b(b({},o),{},{unit:"second"})):"",a?E(a,b(b({},o),{},{unit:"minute"})):"",n?E(n,b(b({},o),{},{unit:"hour"})):"").trim()},formatDecimal:function(){var r=Object(p.sprintf)(// translators: %s: number of seconds with "s" as the abbreviated unit.
Object(p.__)("%ds","google-site-kit"),i);if(0===t)return r;var e=Object(p.sprintf)(// translators: %s: number of minutes with "m" as the abbreviated unit.
Object(p.__)("%dm","google-site-kit"),a),o=Object(p.sprintf)(// translators: %s: number of hours with "h" as the abbreviated unit.
Object(p.__)("%dh","google-site-kit"),n);return Object(p.sprintf)(
/* translators: 1: formatted seconds, 2: formatted minutes, 3: formatted hours */
Object(p._x)("%3$s %2$s %1$s","duration of time: hh mm ss","google-site-kit"),i?r:"",a?e:"",n?o:"").trim()}}},w=function(t){return 1e6<=t?Math.round(t/1e5)/10:1e4<=t?Math.round(t/1e3):1e3<=t?Math.round(t/100)/10:t},k=function(t){var r={minimumFractionDigits:1,maximumFractionDigits:1};return 1e6<=t?Object(p.sprintf)(// translators: %s: an abbreviated number in millions.
Object(p.__)("%sM","google-site-kit"),E(w(t),t%10==0?{}:r)):1e4<=t?Object(p.sprintf)(// translators: %s: an abbreviated number in thousands.
Object(p.__)("%sK","google-site-kit"),E(w(t))):1e3<=t?Object(p.sprintf)(// translators: %s: an abbreviated number in thousands.
Object(p.__)("%sK","google-site-kit"),E(w(t),t%10==0?{}:r)):E(t,{signDisplay:"never",maximumFractionDigits:1})};function j(t){var r={};return"%"===t?r={style:"percent",maximumFractionDigits:2}:"s"===t?r={style:"duration",unitDisplay:"narrow"}:t&&"string"==typeof t?r={style:"currency",currency:t}:Object(l.isPlainObject)(t)&&(r=b({},t)),r}function _(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};t=Object(l.isFinite)(t)?t:Number(t),Object(l.isFinite)(t)||(console.warn("Invalid number",t,o()(t)),t=0);var e=j(r),n=e.style,a=void 0===n?"metric":n;return"metric"===a?k(t):"duration"===a?m(t,e):"durationISO"===a?h(t):E(t,e)}var S=g()(console.warn),E=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},e=r.locale,n=void 0===e?N():e,i=f()(r,["locale"]);try{return new Intl.NumberFormat(n,i).format(t)}catch(r){S("Site Kit numberFormat error: Intl.NumberFormat( ".concat(JSON.stringify(n),", ").concat(JSON.stringify(i)," ).format( ").concat(o()(t)," )"),r.message)}for(var u={currencyDisplay:"narrow",currencySign:"accounting",style:"unit"},c=["signDisplay","compactDisplay"],s={},l=0,d=Object.entries(i);l<d.length;l++){var g=a()(d[l],2),p=g[0],v=g[1];u[p]&&v===u[p]||(c.includes(p)||(s[p]=v))}try{return new Intl.NumberFormat(n,s).format(t)}catch(r){return new Intl.NumberFormat(n).format(t)}},A=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},e=r.locale,n=void 0===e?N():e,a=r.style,i=void 0===a?"long":a,o=r.type,u=void 0===o?"conjunction":o;if(Intl.ListFormat){var c=new Intl.ListFormat(n,{style:i,type:u});return c.format(t)}
/* translators: used between list items, there is a space after the comma. */var s=Object(p.__)(", ","google-site-kit");return t.join(s)},N=function(){var r=arguments.length>0&&void 0!==arguments[0]?arguments[0]:t,e=Object(l.get)(r,["_googlesitekitLegacyData","locale"]);if(e){var n=e.match(/^(\w{2})?(_)?(\w{2})/);if(n&&n[0])return n[0].replace(/_/g,"-")}return r.navigator.language}}).call(this,e(24))},72:function(t,r,e){"use strict";e.d(r,"a",(function(){return o})),e.d(r,"b",(function(){return u}));var n=e(30),a=e.n(n),i=e(79),o=function(t){var r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return{__html:i.a.sanitize(t,r)}};function u(t){var r,e="object"===a()(t)?t.toString():t;return null==e||null===(r=e.replace)||void 0===r?void 0:r.call(e,/\/+$/,"")}},77:function(t,r,e){"use strict";e.d(r,"b",(function(){return a})),e.d(r,"a",(function(){return i})),e.d(r,"c",(function(){return o})),e.d(r,"d",(function(){return u}));var n=e(105);function a(t){try{return new URL(t).pathname}catch(t){}return null}function i(t,r){try{return new URL(r,t).href}catch(t){}return("string"==typeof t?t:"")+("string"==typeof r?r:"")}function o(t){return"string"!=typeof t?t:t.replace(/^https?:\/\/(www\.)?/i,"").replace(/\/$/,"")}function u(t,r){if(!Object(n.a)(t))return t;if(t.length<=r)return t;var e=new URL(t),a=t.replace(e.origin,"");if(a.length<r)return a;var i=a.length-Math.floor(r)+1;return"…"+a.substr(i)}},79:function(t,r,e){"use strict";(function(t){e.d(r,"a",(function(){return a}));var n=e(143),a=e.n(n)()(t)}).call(this,e(24))},8:function(t,r,e){"use strict";(function(t){e.d(r,"p",(function(){return v})),e.d(r,"d",(function(){return b})),e.d(r,"E",(function(){return m})),e.d(r,"h",(function(){return h}));var n=e(13),a=e(32);e.d(r,"B",(function(){return a.b})),e.d(r,"C",(function(){return a.c}));var i=e(72);e.d(r,"y",(function(){return i.a})),e.d(r,"D",(function(){return i.b}));var o=e(92);e.d(r,"A",(function(){return o.a}));e(93);var u=e(70);e.d(r,"i",(function(){return u.a})),e.d(r,"n",(function(){return u.b})),e.d(r,"t",(function(){return u.c})),e.d(r,"w",(function(){return u.d})),e.d(r,"x",(function(){return u.e}));var c=e(94);e.d(r,"u",(function(){return c.a}));var s=e(95);e.d(r,"f",(function(){return s.a})),e.d(r,"g",(function(){return s.b}));var f=e(61);e.d(r,"a",(function(){return f.a})),e.d(r,"b",(function(){return f.b})),e.d(r,"c",(function(){return f.e})),e.d(r,"j",(function(){return f.f})),e.d(r,"l",(function(){return f.g})),e.d(r,"o",(function(){return f.h})),e.d(r,"z",(function(){return f.k}));var l=e(96);e.d(r,"e",(function(){return l.a})),e.d(r,"k",(function(){return l.b}));var d=e(77);e.d(r,"m",(function(){return d.a})),e.d(r,"q",(function(){return d.b})),e.d(r,"v",(function(){return d.c}));var g=e(97);e.d(r,"s",(function(){return g.a}));var p=e(98);e.d(r,"r",(function(){return p.a})),t._gsktag=a.b;var v=function(t){switch(t){case"minute":return 60;case"hour":return 3600;case"day":return 86400;case"week":return 604800;case"month":return 2592e3;case"year":return 31536e3}};function b(t,r){var e=function(t){return"0"===t||0===t};if(e(t)&&e(r))return 0;if(e(t)||Number.isNaN(t))return null;var n=(r-t)/t;return Number.isNaN(n)||!Number.isFinite(n)?null:n}var m=function(t){try{return JSON.parse(t)&&!!t}catch(t){return!1}},h=function(t){if(!t)return"";var r=t.replace(/&#(\d+);/g,(function(t,r){return String.fromCharCode(r)})).replace(/(\\)/g,"");return Object(n.unescape)(r)}}).call(this,e(24))},80:function(t,r,e){"use strict";(function(t){var n=e(0),a=e.n(n),i=e(12),o=e.n(i);function ChangeArrow(r){var e=r.direction,n=r.invertColor,a=r.width,i=r.height;return t.createElement("svg",{className:o()("googlesitekit-change-arrow","googlesitekit-change-arrow--".concat(e),{"googlesitekit-change-arrow--inverted-color":n}),width:a,height:i,viewBox:"0 0 10 10",fill:"none",xmlns:"http://www.w3.org/2000/svg"},t.createElement("path",{d:"M5.625 10L5.625 2.375L9.125 5.875L10 5L5 -1.76555e-07L-2.7055e-07 5L0.875 5.875L4.375 2.375L4.375 10L5.625 10Z",fill:"currentColor"}))}ChangeArrow.propTypes={direction:a.a.string,invertColor:a.a.bool,width:a.a.number,height:a.a.number},ChangeArrow.defaultProps={direction:"up",invertColor:!1,width:9,height:9},r.a=ChangeArrow}).call(this,e(3))},92:function(t,r,e){"use strict";e.d(r,"a",(function(){return u}));var n=e(30),a=e.n(n),i=e(88),o=e.n(i),u=function(t){return o()(JSON.stringify(function t(r){var e={};return Object.keys(r).sort().forEach((function(n){var i=r[n];i&&"object"===a()(i)&&!Array.isArray(i)&&(i=t(i)),e[n]=i})),e}(t)))}},93:function(t,r,e){"use strict";(function(t){e(48),e(49)}).call(this,e(24))},94:function(t,r,e){"use strict";function n(t){return t.replace(new RegExp("\\[([^\\]]+)\\]\\((https?://[^/]+\\.\\w+/?.*?)\\)","gi"),'<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')}function a(t){return"<p>".concat(t.replace(/\n{2,}/g,"</p><p>"),"</p>")}function i(t){return t.replace(/\n/gi,"<br>")}function o(t){for(var r=t,e=0,o=[n,a,i];e<o.length;e++){r=(0,o[e])(r)}return r}e.d(r,"a",(function(){return o}))},95:function(t,r,e){"use strict";e.d(r,"b",(function(){return n})),e.d(r,"a",(function(){return a}));var n=function(t){return t=parseFloat(t),isNaN(t)||0===t?[0,0,0,0]:[Math.floor(t/60/60),Math.floor(t/60%60),Math.floor(t%60),Math.floor(1e3*t)-1e3*Math.floor(t)]},a=function(t){var r=t&&!Number.isInteger(t)?new Date(t).getTime():t;return isNaN(r)||!r?0:r}},96:function(t,r,e){"use strict";(function(t){e.d(r,"b",(function(){return i})),e.d(r,"a",(function(){return o}));var n=e(219),a=e(80),i=function(r){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};if(Number.isNaN(Number(r)))return"";var i=e.invertColor,o=void 0!==i&&i;return Object(n.a)(t.createElement(a.a,{direction:r>0?"up":"down",invertColor:o}))},o=function(t,r){return t>0&&r>0?t/r-1:t>0?1:r>0?-1:0}}).call(this,e(3))},97:function(t,r,e){"use strict";function n(t){var r=parseFloat(t)||0;return!!Number.isInteger(r)&&r>0}e.d(r,"a",(function(){return n}))},98:function(t,r,e){"use strict";function n(t){if("number"==typeof t)return!0;var r=(t||"").toString();return!!r&&!isNaN(r)}e.d(r,"a",(function(){return n}))}},[[1034,1,0]]]);