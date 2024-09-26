wpJsonpAmeliaBookingPlugin([22],{1571:function(t,e,s){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var a=s(694),i=s.n(a);e.default={data:function(){return{email:"",isValidEmail:!0,blogPosts:[],changelog:{version:"7.8",starter:{feature:["Redesigned Event Calendar 2.0 - Streamlined event booking experience for your customers"],improvement:["Increased Event description length limit","Improved connection between WP user and Amelia customer/employee","Added possibility to add new customers to group bookings by creating a new appointment"],translations:["Updated Turkish translation"],bugfix:["Fixed issue with displaying time slots on iPhones","Fixed issue with showing Customer Panel sidebar on Safari","Changed am-input class condition for Step-by-step and Catalog Booking forms","Fixed issue with duplicating categories","Fixed issue with the new customer panel when password is not required","Fixed issue with un-hiding employee on the back-end","Fixed issue with event not blocking appointments time slots","Fixed issue with timezone when an employee is logged to WP and in customer notifications when booking from panel","Fixed issue with displaying employee breaks on the calendar","Fixed issue with auto-filling of the Panel password and phone input fields in Employee modal",'Fixed issue with Extras tab when appointment status is changed to "rejected"',"Fixed issue with the booking form when the logged in employee is not assigned to services"],other:["Other small bug fixes and stability improvements"]},basic:{feature:[],improvement:[],translations:[],bugfix:["Fixed issue with missing note for cron command on follow up notification","Fixed issue with booking (group) recurring appointments on the back-end","Fixed issue with un-hiding a location on the back-end","Fixed issue with prices info for events with Custom pricing by date range","Fixed issue with recurring popup labels on Customize","Fixed issue with Custom Fields in case of word-wrap of the text for the content and when pixel size is changed",'Fixed issue with "Every" dropdown in the recurring appointment'],other:[]},pro:{feature:["Event Waiting List - Allow customers to join a waiting list and hold their spot until openings become available"],improvement:["Improved WhatsApp notifications in case when the template contains a location in header","Added new property isPackageAppointment that will show whether the appointment booked is a part of a package"],translations:[],bugfix:["Fixed issue with editing group appointments on the Packages page","Fixed issue with Direct charge when Application Fee Amount is set to 0%","Fixed issue with Catalog Form in case when package is preselected and service is hidden","Fixed issue with webhooks when booking appointments in packages and changing status","Fixed issue with the timezone placeholder in WhatsApp notifications"],other:[]},developer:{feature:[],improvement:[],translations:[],bugfix:[],other:[]}},loading:!1}},methods:{clearValidation:function(){this.isValidEmail=!0},submitForm:function(){/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/.test(this.email)?(this.isValidEmail=!0,this.$refs.emailForm.submit()):this.isValidEmail=!1},getThisAndLowerLicences:function(){return this.$root.licence.isStarter?["starter"]:this.$root.licence.isBasic?["starter","basic"]:this.$root.licence.isPro?["starter","basic","pro"]:this.$root.licence.isDeveloper?["starter","basic","pro","developer"]:void 0},getHigherLicences:function(){return this.$root.licence.isStarter?["basic","pro","developer"]:this.$root.licence.isBasic?["pro","developer"]:this.$root.licence.isPro?["developer"]:this.$root.licence.isDeveloper?[]:void 0},getLicencesItems:function(t){var e=this,s=[];return t.forEach(function(t){e.changelog[t].feature.forEach(function(e){s.push({licence:t,type:"Feature",text:e})})}),t.forEach(function(t){e.changelog[t].improvement.forEach(function(e){s.push({licence:t,type:"Improvement",text:e})})}),t.forEach(function(t){e.changelog[t].translations.forEach(function(e){s.push({licence:t,type:"Translations",text:e})})}),t.forEach(function(t){e.changelog[t].bugfix.forEach(function(e){s.push({licence:t,type:"BugFix",text:e})})}),t.forEach(function(t){e.changelog[t].other.forEach(function(e){s.push({licence:t,text:e})})}),s},getNews:function(){var t=this;this.loading=!0,this.$http.get(this.$root.getAjaxUrl+"/whats-new").then(function(e){t.blogPosts=e.data.data.blogPosts?e.data.data.blogPosts.slice(0,6):[],t.loading=!1}).catch(function(e){t.loading=!1,console.log(e)})},getIconType:function(t){var e=t.toLowerCase();switch(e){case"improvement":case"bugfix":case"feature":case"translations":return e;default:return"plus"}}},created:function(){this.getNews()},components:{PageHeader:i.a}}},1572:function(t,e){t.exports={render:function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"am-wrap",attrs:{id:"am-whats-new"}},[s("div",{staticClass:"am-body"},[s("page-header"),t._v(" "),s("div",{staticClass:"am-whats-new-welcome am-section am-whats-new-section"},[s("div",{staticClass:"am-whats-new-welcome-left"},[s("div",{staticClass:"am-whats-new-welcome-title"},[t._v(t._s(t.$root.labels.welcome_to_amelia))]),t._v(" "),s("div",{staticClass:"am-whats-new-welcome-subtitle"},[t._v(t._s(t.$root.labels.welcome_congratz))]),t._v(" "),s("a",{staticClass:"am-whats-new-btn",attrs:{href:"https://www.youtube.com/c/AmeliaWordPressBookingPlugin",target:"_blank"}},[t._v("\n          "+t._s(t.$root.labels.discover_amelia)+"\n        ")])]),t._v(" "),s("div",{staticClass:"am-whats-new-welcome-right"},[s("img",{attrs:{src:t.$root.getUrl+"public/img/am-whats-new-welcome.webp"}})])]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog am-section am-whats-new-section"},[s("div",{staticClass:"am-whats-new-changelog-left"},[s("div",{staticClass:"am-whats-new-changelog-header"},[s("div",{staticClass:"am-whats-new-changelog-title am-whats-new-blog-title-text"},[t._v("\n            "+t._s(t.$root.labels.amelia_changelog)+"\n          ")]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog-subtitle am-whats-new-blog-subtitle-text"},[t._v("\n            "+t._s(t.$root.labels.current_version)+" "+t._s(t.changelog.version)+"\n          ")])]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog-version"},[s("div",{staticClass:"am-whats-new-changelog-version-title"},[t._v("\n            "+t._s(t.$root.labels.version)+" "+t._s(t.changelog.version)+"\n          ")]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog-subtitle am-whats-new-blog-subtitle-text"},[t._v("\n            "+t._s(t.$root.labels.version_subtitle)+"\n          ")])]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog-list"},[s("p",{staticClass:"am-whats-new-changelog-list-title"},[t._v(t._s(t.$root.labels.included_plan_your))]),t._v(" "),t._l(t.getLicencesItems(t.getThisAndLowerLicences()),function(e,a){return s("div",{key:e.licence+e.type+a,staticClass:"am-whats-new-changelog-list-item"},[s("div",{staticClass:"am-whats-new-changelog-list-item-img-holder"},[e.type?s("img",{attrs:{src:t.$root.getUrl+"public/img/am-"+t.getIconType(e.type)+".svg"}}):t._e()]),t._v(" "),e.text?s("div",{staticClass:"am-whats-new-blog-subtitle-text",domProps:{innerHTML:t._s(e.text.replace(":",""))}}):t._e()])}),t._v(" "),!t.$root.licence.isDeveloper&&t.getLicencesItems(t.getHigherLicences()).length?s("p",{staticClass:"am-whats-new-changelog-list-title"},[t._v(t._s(t.$root.labels.included_plan_higher))]):t._e(),t._v(" "),t._l(t.getLicencesItems(t.getHigherLicences()),function(e,a){return t.$root.licence.isDeveloper?t._e():s("div",{key:a,staticClass:"am-whats-new-changelog-list-item"},[s("div",{staticClass:"am-whats-new-changelog-list-item-img-holder"},[e.type?s("img",{attrs:{src:t.$root.getUrl+"public/img/am-"+t.getIconType(e.type)+".svg"}}):t._e()]),t._v(" "),e.text?s("div",{staticClass:"am-whats-new-blog-subtitle-text",domProps:{innerHTML:t._s(e.text.replace(":",""))}}):t._e()])}),t._v(" "),s("a",{staticClass:"am-whats-new-changelog-link",attrs:{href:"https://wpamelia.com/changelog/",target:"_blank",rel:"nofollow"}},[t._v("\n            "+t._s(t.$root.labels.see_previous_versions)+"\n            "),s("img",{attrs:{src:t.$root.getUrl+"public/img/am-arrow-upper-right.svg"}})])],2)]),t._v(" "),s("div",{staticClass:"am-whats-new-changelog-right"},[s("div",{staticClass:"am-whats-new-blog-success-stories am-whats-new-blog-box"},[s("img",{attrs:{src:t.$root.getUrl+"public/img/am-success-stories.webp"}}),t._v(" "),s("div",{staticClass:"am-whats-new-blog-success-stories-title am-whats-new-blog-title-text"},[t._v("\n            "+t._s(t.$root.labels.take_a_look)+"\n          ")]),t._v(" "),s("a",{staticClass:"am-whats-new-btn",attrs:{href:"https://wpamelia.com/success-stories/",target:"_blank",rel:"nofollow"}},[t._v("\n            "+t._s(t.$root.labels.read_success_stories)+"\n          ")])])])]),t._v(" "),s("div",{staticClass:"am-whats-new-blog am-section am-whats-new-section"},[s("div",{staticClass:"am-whats-new-blog-left"},[s("div",{staticClass:"am-whats-new-blog-title am-whats-new-blog-title-text"},[t._v("\n          "+t._s(t.$root.labels.news_blog)),s("img",{attrs:{src:t.$root.getUrl+"public/img/am-ringing-bel.png"}})]),t._v(" "),t.loading?s("div",{staticClass:"am-whats-new-loader"},[s("img",{attrs:{src:t.$root.getUrl+"public/img/spinner.svg"}})]):s("div",{staticClass:"am-whats-new-blog-list"},t._l(t.blogPosts,function(e){return s("div",{key:e.href,staticClass:"am-whats-new-blog-list-item"},[s("p",[t._v(t._s(e.title))]),t._v(" "),s("a",{attrs:{href:e.href,target:"_blank",rel:"nofollow"}},[s("img",{attrs:{src:t.$root.getUrl+"public/img/am-arrow-upper-right.svg"}})])])}),0),t._v(" "),s("div",{staticClass:"am-whats-new-blog-subscribe"},[s("div",{staticClass:"am-whats-new-blog-subscribe-left"},[s("div",{staticClass:"am-whats-new-blog-subscribe-title"},[t._v(t._s(t.$root.labels.keep_up_to_date))]),t._v(" "),s("div",{staticClass:"am-whats-new-blog-subscribe-subtitle am-whats-new-blog-subtitle-text"},[t._v(t._s(t.$root.labels.never_miss_notifications))]),t._v(" "),s("div",{staticClass:"am-whats-new-blog-subscribe-form"},[s("form",{ref:"emailForm",staticClass:"am-whats-new-blog-subscribe-form",attrs:{action:"https://acumbamail.com/newform/subscribe/ET8rshmNeLvQox6J8U99sSJZ8B1DZo1mhOgs408R0mHYiwgmM/39828/",method:"post"},on:{submit:function(e){return e.preventDefault(),t.submitForm(e)}}},[s("div",{},[s("div",{staticStyle:{width:"100%",position:"relative"}},[t.isValidEmail?t._e():s("span",{staticStyle:{color:"red"},attrs:{id:"am-subscribe-error-msg"}},[t._v("Please enter a valid email address.")]),s("br"),t._v(" "),s("input",{directives:[{name:"model",rawName:"v-model",value:t.email,expression:"email"}],attrs:{id:"r0c0m1i1",name:"email_1",type:"email",placeholder:t.$root.labels.enter_your_email,required:""},domProps:{value:t.email},on:{keyup:t.clearValidation,input:function(e){e.target.composing||(t.email=e.target.value)}}}),s("br"),t._v(" "),s("input",{staticStyle:{position:"absolute",left:"-4900px"},attrs:{type:"text",name:"a781911c",tabindex:"-1",value:"","aria-hidden":"true",id:"a781911c",autocomplete:"off"}}),t._v(" "),s("br"),t._v(" "),s("input",{staticStyle:{position:"absolute",left:"-5000px"},attrs:{type:"email",name:"b781911c",tabindex:"-1",value:"","aria-hidden":"true",id:"b781911c",autocomplete:"off"}}),t._v(" "),s("br"),t._v(" "),s("input",{staticStyle:{position:"absolute",left:"-5100px"},attrs:{type:"checkbox",name:"c781911c",tabindex:"-1","aria-hidden":"true",id:"c781911c",autocomplete:"off"}}),t._v(" "),s("br")])]),t._v(" "),s("input",{attrs:{type:"hidden",name:"ok_redirect",id:"id_redirect",value:"/*You can insert the url that you want to redirect to after a valid registration here */"}}),t._v(" "),s("input",{staticClass:"am-whats-new-btn am-subscribe-btn",attrs:{type:"submit"},domProps:{value:t.$root.labels.subscribe}})])])]),t._v(" "),s("div",{staticClass:"am-whats-new-blog-subscribe-right"},[s("img",{attrs:{src:t.$root.getUrl+"public/img/am-subscribe-box.svg"}})])])]),t._v(" "),s("div",{staticClass:"am-whats-new-blog-right"},[s("div",{staticClass:"am-whats-new-blog-support am-whats-new-blog-box"},[s("img",{attrs:{src:t.$root.getUrl+"public/img/am-contact-support.svg"}}),t._v(" "),s("div",{staticClass:"am-whats-new-blog-title-text"},[t._v("\n            "+t._s(t.$root.labels.need_help)+"\n          ")]),t._v(" "),s("div",{staticClass:"am-whats-new-blog-support-subtitle am-whats-new-blog-subtitle-text"},[t._v("\n            "+t._s(t.$root.labels.our_support_team)+"\n          ")]),t._v(" "),s("a",{staticClass:"am-whats-new-btn",attrs:{href:"https://tmsplugins.ticksy.com/submit/#100012870",target:"_blank"}},[t._v("\n            "+t._s(t.$root.labels.contact_our_support)+"\n          ")])])])])],1)])},staticRenderFns:[]}},1647:function(t,e,s){var a=s(90)(s(1571),s(1572),!1,null,null,null);t.exports=a.exports},654:function(t,e,s){"use strict";e.a={data:function(){return{colors:["1788FB","4BBEC6","FBC22D","FA3C52","D696B8","689BCA","26CC2B","FD7E35","E38587","774DFB","31CDF3","6AB76C","FD5FA1","A697C5"],usedColors:[]}},methods:{deleteImage:function(t){t.pictureThumbPath="",t.pictureFullPath=""},getAppropriateUrlParams:function(t){if("disableUrlParams"in this.$root.settings.activation&&!this.$root.settings.activation.disableUrlParams)return t;var e=JSON.parse(JSON.stringify(t));return["categories","services","packages","employees","providers","providerIds","extras","locations","events","types","dates"].forEach(function(t){if("extras"===t&&t in e){e.extras=JSON.parse(e.extras);var s=[];e.extras.forEach(function(t){s.push(t.id+"-"+t.quantity)}),e.extras=s.length?s:null}t in e&&Array.isArray(e[t])&&e[t].length&&(e[t]=e[t].join(","))}),e},inlineSVG:function(){var t=s(661);t.init({svgSelector:"img.svg-amelia",initClass:"js-inlinesvg"})},inlineSVGCabinet:function(){setTimeout(function(){s(661).init({svgSelector:"img.svg-cabinet",initClass:"js-inlinesvg"})},100)},imageFromText:function(t){var e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},s=arguments.length>2&&void 0!==arguments[2]&&arguments[2],a=this.getNameInitials(t),i=Math.floor(Math.random()*this.colors.length),o=this.colors[i];return this.usedColors.push(this.colors[i]),this.colors.splice(i,1),0===this.colors.length&&(this.colors=this.usedColors,this.usedColors=[]),s?e.firstName?this.$root.getUrl+"public/img/default-employee.svg":e.latitude?this.$root.getUrl+"public/img/default-location.svg":this.$root.getUrl+"public/img/default-service.svg":location.protocol+"//via.placeholder.com/120/"+o+"/fff?text="+a},pictureLoad:function(t,e){if(null!==t){var s=!0===e?t.firstName+" "+t.lastName:t.name;if(void 0!==s)return t.pictureThumbPath=t.pictureThumbPath||this.imageFromText(s),t.pictureThumbPath}},imageLoadError:function(t,e){var s=!0===e?t.firstName+" "+t.lastName:t.name;void 0!==s&&(t.pictureThumbPath=this.imageFromText(s,t,!0))},getNameInitials:function(t){return t.split(" ").map(function(t){return t.charAt(0)}).join("").toUpperCase().substring(0,3).replace(/[^\w\s]/g,"")}}}},661:function(t,e,s){(function(s){var a,i,o,n;n=void 0!==s?s:this.window||this.global,i=[],a=function(t){var e,s={},a=!!document.querySelector&&!!t.addEventListener,i={initClass:"js-inlinesvg",svgSelector:"img.svg"},o=function(){var t={},e=!1,s=0,a=arguments.length;"[object Boolean]"===Object.prototype.toString.call(arguments[0])&&(e=arguments[0],s++);for(var i=function(s){for(var a in s)Object.prototype.hasOwnProperty.call(s,a)&&(e&&"[object Object]"===Object.prototype.toString.call(s[a])?t[a]=o(!0,t[a],s[a]):t[a]=s[a])};a>s;s++){i(arguments[s])}return t},n=function(t){var s=document.querySelectorAll(e.svgSelector),a=function(t,e){return function(){return--t<1?e.apply(this,arguments):void 0}}(s.length,t);Array.prototype.forEach.call(s,function(t,s){var i=t.src||t.getAttribute("data-src"),o=t.attributes,n=new XMLHttpRequest;n.open("GET",i,!0),n.onload=function(){if(n.status>=200&&n.status<400){var s=(new DOMParser).parseFromString(n.responseText,"text/xml").getElementsByTagName("svg")[0];if(s.removeAttribute("xmlns:a"),s.removeAttribute("width"),s.removeAttribute("height"),s.removeAttribute("x"),s.removeAttribute("y"),s.removeAttribute("enable-background"),s.removeAttribute("xmlns:xlink"),s.removeAttribute("xml:space"),s.removeAttribute("version"),Array.prototype.slice.call(o).forEach(function(t){"src"!==t.name&&"alt"!==t.name&&s.setAttribute(t.name,t.value)}),s.classList?s.classList.add("inlined-svg"):s.className+=" inlined-svg",s.setAttribute("role","img"),o.longdesc){var i=document.createElementNS("http://www.w3.org/2000/svg","desc"),l=document.createTextNode(o.longdesc.value);i.appendChild(l),s.insertBefore(i,s.firstChild)}if(o.alt){s.setAttribute("aria-labelledby","title");var r=document.createElementNS("http://www.w3.org/2000/svg","title"),c=document.createTextNode(o.alt.value);r.appendChild(c),s.insertBefore(r,s.firstChild)}t.parentNode.replaceChild(s,t),a(e.svgSelector)}else console.error("There was an error retrieving the source of the SVG.")},n.onerror=function(){console.error("There was an error connecting to the origin server.")},n.send()})};return s.init=function(t,s){a&&(e=o(i,t||{}),n(s||function(){}),document.documentElement.className+=" "+e.initClass)},s}(n),void 0===(o="function"==typeof a?a.apply(e,i):a)||(t.exports=o)}).call(e,s(37))},694:function(t,e,s){var a=s(90)(s(697),s(698),!1,null,null,null);t.exports=a.exports},697:function(t,e,s){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var a=s(91),i=s(336),o=s(654);e.default={mixins:[a.a,i.a,o.a],props:["oldCustomize","appointmentsApproved","appointmentsPending","employeesTotal","customersTotal","locationsTotal","packagesTotal","resourcesTotal","servicesTotal","categoriesTotal","financeTotal","addNewTaxBtnDisplay","addNewCouponBtnDisplay","addNewCustomFieldBtnDisplay","locations","categories","bookableType","params","fetched"],methods:{showMainCustomize:function(){this.$emit("showMainCustomize",null)},showDialogCustomer:function(){this.$emit("newCustomerBtnClicked",null)},showDialogAppointment:function(){this.$emit("newAppointmentBtnClicked",null)},showDialogEvent:function(){this.$emit("newEventBtnClicked",null)},showDialogEmployee:function(){this.$emit("newEmployeeBtnClicked")},showDialogLocation:function(){this.$emit("newLocationBtnClicked")},showDialogService:function(){this.$emit("newServiceBtnClicked")},showDialogPackage:function(){this.$emit("newPackageBtnClicked")},showDialogPackageBooking:function(){this.$emit("newPackageBookingBtnClicked")},showDialogResource:function(){this.$emit("newResourceBtnClicked")},showDialogTax:function(){this.$emit("newTaxBtnClicked")},showDialogCoupon:function(){this.$emit("newCouponBtnClicked")},showDialogCustomFields:function(){this.$emit("newCustomFieldBtnClicked")},selectAllInCategory:function(t){this.$emit("selectAllInCategory",t)},changeFilter:function(){this.$emit("changeFilter")}}}},698:function(t,e){t.exports={render:function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"am-page-header am-section"},[s("el-row",{attrs:{type:"wpamelia-calendar"===t.$router.currentRoute.name?"":"flex",align:"middle"}},[s("el-col",{attrs:{span:"wpamelia-calendar"===t.$router.currentRoute.name?6:18}},[s("div",{staticClass:"am-logo"},[s("img",{staticClass:"logo-big",attrs:{width:"92",src:t.$root.getUrl+"public/img/amelia-logo-horizontal.svg"}}),t._v(" "),s("img",{staticClass:"logo-small",attrs:{width:"28",src:t.$root.getUrl+"public/img/amelia-logo-symbol.svg"}})]),t._v(" "),s("h1",{staticClass:"am-page-title"},[t._v("\n        "+t._s(t.bookableType?t.$root.labels[t.bookableType]:t.$router.currentRoute.meta.title)+"\n\n        "),t._v(" "),t.appointmentsApproved>=0?s("span",{staticClass:"am-appointments-number approved"},[t._v("\n          "+t._s(t.appointmentsApproved)+"\n        ")]):t._e(),t._v(" "),t.appointmentsPending>=0?s("span",{staticClass:"am-appointments-number pending"},[t._v("\n          "+t._s(t.appointmentsPending)+"\n        ")]):t._e(),t._v(" "),t.employeesTotal>=0&&!0===t.$root.settings.capabilities.canReadOthers?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.employeesTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.customersTotal>=0?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.customersTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.locationsTotal>=0?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.locationsTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.servicesTotal>=0&&"services"===t.bookableType?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.servicesTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.packagesTotal>=0&&"packages"===t.bookableType?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.packagesTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.resourcesTotal>=0&&"resources"===t.bookableType?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.resourcesTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e(),t._v(" "),t.financeTotal>=0?s("span",[s("span",{staticClass:"total-number"},[t._v(t._s(t.financeTotal))]),t._v(" "+t._s(t.$root.labels.total)+"\n        ")]):t._e()])]),t._v(" "),s("el-col",{staticClass:"align-right v-calendar-column",attrs:{span:"wpamelia-calendar"===t.$router.currentRoute.name?18:6}},["wpamelia-appointments"===t.$router.currentRoute.name&&(!0===t.$root.settings.capabilities.canWriteOthers||"provider"===this.$root.settings.role&&this.$root.settings.roles.allowWriteAppointments)?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogAppointment}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.new_appointment))])]):t._e(),t._v(" "),"wpamelia-events"===t.$router.currentRoute.name&&(!0===t.$root.settings.capabilities.canWriteOthers||"provider"===this.$root.settings.role&&this.$root.settings.roles.allowWriteEvents)?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogEvent}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.new_event))])]):t._e(),t._v(" "),"wpamelia-employees"===t.$router.currentRoute.name&&!0===t.$root.settings.capabilities.canWrite&&!0===t.$root.settings.capabilities.canWriteOthers?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogEmployee}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_employee))])]):t._e(),t._v(" "),"wpamelia-customers"===t.$router.currentRoute.name&&!0===t.$root.settings.capabilities.canWrite?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogCustomer}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_customer))])]):t._e(),t._v(" "),"wpamelia-locations"===t.$router.currentRoute.name&&!0===t.$root.settings.capabilities.canWrite?s("el-button",{staticClass:"am-dialog-create",class:t.licenceClassDisabled(),attrs:{type:"primary",disabled:t.notInLicence()},on:{click:t.showDialogLocation}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_location))])]):t._e(),t._v(" "),"wpamelia-services"===t.$router.currentRoute.name&&t.categoriesTotal>0&&!0===t.$root.settings.capabilities.canWrite&&"services"===t.bookableType?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogService}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_service))])]):t._e(),t._v(" "),"wpamelia-services"===t.$router.currentRoute.name&&!0===t.$root.settings.capabilities.canWrite&&"packages"===t.bookableType?s("el-button",{staticClass:"am-dialog-create",class:t.licenceClassDisabled("pro"),attrs:{type:"primary",disabled:t.notInLicence("pro")},on:{click:function(e){t.packagesTotal>=0?t.showDialogPackage():t.showDialogPackageBooking()}}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.packagesTotal>=0?t.$root.labels.add_package:t.$root.labels.book_package))])]):t._e(),t._v(" "),"wpamelia-services"===t.$router.currentRoute.name&&!0===t.$root.settings.capabilities.canWrite&&"resources"===t.bookableType?s("el-button",{staticClass:"am-dialog-create",class:t.licenceClassDisabled("pro"),attrs:{type:"primary",disabled:t.notInLicence("pro")},on:{click:function(e){return t.showDialogResource()}}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_resource))])]):t._e(),t._v(" "),"wpamelia-finance"===t.$router.currentRoute.name&&t.addNewTaxBtnDisplay&&!0===t.$root.settings.capabilities.canWrite?s("el-button",{staticClass:"am-dialog-create",class:t.licenceClassDisabled(),attrs:{type:"primary",disabled:t.notInLicence()},on:{click:t.showDialogTax}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.new_tax))])]):t._e(),t._v(" "),"wpamelia-finance"===t.$router.currentRoute.name&&t.addNewCouponBtnDisplay&&!0===t.$root.settings.capabilities.canWrite?s("el-button",{staticClass:"am-dialog-create",class:t.licenceClassDisabled("starter"),attrs:{type:"primary",disabled:t.notInLicence("starter")},on:{click:t.showDialogCoupon}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.new_coupon))])]):t._e(),t._v(" "),s("transition",{attrs:{name:"fade"}},["wpamelia-cf"===t.$router.currentRoute.name&&t.addNewCustomFieldBtnDisplay?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogCustomFields}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.add_custom_field))])]):t._e()],1),t._v(" "),"wpamelia-dashboard"===t.$router.currentRoute.name?s("div",{staticClass:"v-calendar-column"},[s("div",{staticClass:"el-form-item__content"},[s("v-date-picker",{attrs:{mode:"range","popover-visibility":"focus","popover-direction":"bottom","popover-align":"right","tint-color":"#1A84EE","show-day-popover":!1,"input-props":{class:"el-input__inner"},"is-expanded":!1,"is-required":!0,"input-class":"el-input__inner",formats:t.vCalendarFormats,"is-double-paned":!0},on:{input:t.changeFilter},model:{value:t.params.dates,callback:function(e){t.$set(t.params,"dates",e)},expression:"params.dates"}})],1)]):t._e(),t._v(" "),"wpamelia-calendar"===t.$router.currentRoute.name?s("div",{staticClass:"am-calendar-header-filters"},[s("el-form",{staticClass:"demo-form-inline",attrs:{inline:!0}},[s("el-form-item",{attrs:{label:t.$root.labels.services+":"}},[s("el-select",{attrs:{multiple:"",filterable:"","collapse-tags":"",loading:!t.fetched,placeholder:t.$root.labels.all_services},on:{change:t.changeFilter},model:{value:t.params.services,callback:function(e){t.$set(t.params,"services",e)},expression:"params.services"}},t._l(t.categories,function(e){return s("div",{key:e.id},[s("div",{staticClass:"am-drop-parent",on:{click:function(s){return t.selectAllInCategory(e.id)}}},[s("span",[t._v(t._s(e.name))])]),t._v(" "),t._l(e.serviceList,function(t){return s("el-option",{key:t.id,staticClass:"am-drop-child",attrs:{label:t.name,value:t.id}})})],2)}),0)],1),t._v(" "),s("el-form-item",{directives:[{name:"show",rawName:"v-show",value:t.locations.length,expression:"locations.length"}],attrs:{label:t.$root.labels.locations+":"}},[s("el-select",{attrs:{multiple:"",clearable:"","collapse-tags":"",placeholder:t.$root.labels.all_locations,loading:!t.fetched},on:{change:t.changeFilter},model:{value:t.params.locations,callback:function(e){t.$set(t.params,"locations",e)},expression:"params.locations"}},t._l(t.locations,function(t){return s("el-option",{key:t.id,attrs:{label:t.name,value:t.id}})}),1)],1)],1),t._v(" "),"wpamelia-calendar"===t.$router.currentRoute.name&&("admin"===t.$root.settings.role||"manager"===t.$root.settings.role||"provider"===t.$root.settings.role&&t.$root.settings.roles.allowWriteAppointments)?s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showDialogAppointment}},[s("i",{staticClass:"el-icon-plus"}),t._v(" "),s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.new_appointment))])]):t._e()],1):t._e(),t._v(" "),t.oldCustomize&&"wpamelia-customize"===t.$router.currentRoute.name?s("div",{staticClass:"am-calendar-header-filters"},[s("el-button",{staticClass:"am-dialog-create",attrs:{type:"primary"},on:{click:t.showMainCustomize}},[s("span",{staticClass:"button-text"},[t._v(t._s(t.$root.labels.go_back))])])],1):t._e()],1)],1)],1)},staticRenderFns:[]}}});