!function(e){function t(t){for(var n,a,s=t[0],l=t[1],u=t[2],d=0,f=[];d<s.length;d++)a=s[d],Object.prototype.hasOwnProperty.call(i,a)&&i[a]&&f.push(i[a][0]),i[a]=0;for(n in l)Object.prototype.hasOwnProperty.call(l,n)&&(e[n]=l[n]);for(c&&c(t);f.length;)f.shift()();return o.push.apply(o,u||[]),r()}function r(){for(var e,t=0;t<o.length;t++){for(var r=o[t],n=!0,s=1;s<r.length;s++){var l=r[s];0!==i[l]&&(n=!1)}n&&(o.splice(t--,1),e=a(a.s=r[0]))}return e}var n={},i={"contao-multifileupload-bundle":0},o=[];function a(t){if(n[t])return n[t].exports;var r=n[t]={i:t,l:!1,exports:{}};return e[t].call(r.exports,r,r.exports,a),r.l=!0,r.exports}a.m=e,a.c=n,a.d=function(e,t,r){a.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},a.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},a.t=function(e,t){if(1&t&&(e=a(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(a.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var n in e)a.d(r,n,function(t){return e[t]}.bind(null,n));return r},a.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return a.d(t,"a",t),t},a.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},a.p="/bundles/heimrichhannotcontaomultifileupload/";var s=window.webpackJsonp=window.webpackJsonp||[],l=s.push.bind(s);s.push=t,s=s.slice();for(var u=0;u<s.length;u++)t(s[u]);var c=l;o.push(["mEo4","dropzone"]),r()}({CkLv:function(e,t,r){},Fqrg:function(e,t){window.NodeList&&!NodeList.prototype.forEach&&(NodeList.prototype.forEach=function(e,t){t=t||window;for(var r=0;r<this.length;r++)e.call(t,this[r],r,this)})},YuTi:function(e,t){e.exports=function(e){return e.webpackPolyfill||(e.deprecate=function(){},e.paths=[],e.children||(e.children=[]),Object.defineProperty(e,"loaded",{enumerable:!0,get:function(){return e.l}}),Object.defineProperty(e,"id",{enumerable:!0,get:function(){return e.i}}),e.webpackPolyfill=1),e}},mEo4:function(e,t,r){"use strict";r.r(t);r("Fqrg");function n(e){var t=this.parentNode,r=arguments.length,n=+(t&&"object"==typeof e);if(t){for(;r-- >n;)t&&"object"!=typeof arguments[r]&&(arguments[r]=document.createTextNode(arguments[r])),t||!arguments[r].parentNode?t.insertBefore(this.previousSibling,arguments[r]):arguments[r].parentNode.removeChild(arguments[r]);n&&t.replaceChild(e,this)}}(function(e){var t=e.Element.prototype;"function"!=typeof t.matches&&(t.matches=t.msMatchesSelector||t.mozMatchesSelector||t.webkitMatchesSelector||function(e){for(var t=(this.document||this.ownerDocument).querySelectorAll(e),r=0;t[r]&&t[r]!==this;)++r;return Boolean(t[r])}),"function"!=typeof t.closest&&(t.closest=function(e){for(var t=this;t&&1===t.nodeType;){if(t.matches(e))return t;t=t.parentNode}return null})})(window),Element.prototype.replaceWith||(Element.prototype.replaceWith=n),CharacterData.prototype.replaceWith||(CharacterData.prototype.replaceWith=n),DocumentType.prototype.replaceWith||(CharacterData.prototype.replaceWith=n);var i=class{static removeFromArray(e,t){for(var r=0;r<t.length;r++)JSON.stringify(e)==JSON.stringify(t[r])&&t.splice(r,1);return t}};var o=class{static getTextWithoutChildren(e,t){let r=e.clone();return r.children().remove(),void 0!==t&&!0===t?r.text():r.text().trim()}static scrollTo(e,t=0,r=0,n=!1){let i=e.getBoundingClientRect().top+window.pageYOffset-t;setTimeout(()=>{this.elementInViewport(e)&&!0!==n||("scrollBehavior"in document.documentElement.style?window.scrollTo({top:i,behavior:"smooth"}):window.scrollTo(0,i))},r)}static elementInViewport(e){let t=e.offsetTop,r=e.offsetLeft,n=e.offsetWidth,i=e.offsetHeight;for(;e.offsetParent;)t+=(e=e.offsetParent).offsetTop,r+=e.offsetLeft;return t<window.pageYOffset+window.innerHeight&&r<window.pageXOffset+window.innerWidth&&t+i>window.pageYOffset&&r+n>window.pageXOffset}static getAllParentNodes(e){for(var t=[];e;)t.unshift(e),e=e.parentNode;for(var r=0;r<t.length;r++)t[r]===document&&t.splice(r,1);return t}};var a=class{static isTruthy(e){return null!=e}static call(e){"function"==typeof e&&e.apply(this,Array.prototype.slice.call(arguments,1))}};var s=class{static addDynamicEventListener(e,t,r,n,i){void 0===n&&(n=document),n.addEventListener(e,(function(e){let n;a.isTruthy(i)?n=[e.target]:e.target!==document&&(n=o.getAllParentNodes(e.target)),Array.isArray(n)?n.reverse().forEach((function(n){n&&n.matches(t)&&r(n,e)})):document.querySelectorAll(t).forEach((function(t){r(t,e)}))}))}static createEventObject(e,t=!1,r=!1,n=!1){if("function"==typeof Event)return new Event(e,{bubbles:t,cancelable:r,composed:n});{let n=document.createEvent("Event");return n.initEvent(e,t,r),n}}};var l=class{static getParameterByName(e,t){t||(t=window.location.href),e=e.replace(/[\[\]]/g,"\\$&");let r=new RegExp("[?&]"+e+"(=([^&#]*)|&|#|$)").exec(t);return r?r[2]?decodeURIComponent(r[2].replace(/\+/g," ")):"":null}static addParameterToUri(e,t,r){e||(e=window.location.href);let n,i=new RegExp("([?&])"+t+"=.*?(&|#|$)(.*)","gi");if(i.test(e))return null!=r?e.replace(i,"$1"+t+"="+r+"$2$3"):(n=e.split("#"),e=n[0].replace(i,"$1$3").replace(/(&|\?)$/,""),void 0!==n[1]&&null!==n[1]&&(e+="#"+n[1]),e);if(null!=r){let i=-1!==e.indexOf("?")?"&":"?";return n=e.split("#"),e=n[0]+i+t+"="+r,void 0!==n[1]&&null!==n[1]&&(e+="#"+n[1]),e}return e}static addParametersToUri(e,t){if(t instanceof FormData)for(let r of t.entries())t.has(r[0])&&(e=this.addParameterToUri(e,r[0],r[1]));else for(let r in t)t.hasOwnProperty(r)&&(e=this.addParameterToUri(e,r,t[r]));return e}static removeParameterFromUri(e,t){let r=e.split("?");if(r.length>=2){let n=encodeURIComponent(t)+"=",i=r[1].split(/[&;]/g);for(let e=i.length;e-- >0;)-1!==i[e].lastIndexOf(n,0)&&i.splice(e,1);return e=r[0]+"?"+i.join("&")}return e}static removeParametersFromUri(e,t){for(let r in t)t.hasOwnProperty(r)&&(e=this.removeParameterFromUri(e,r));return e}static replaceParameterInUri(e,t,r){this.addParameterToUri(this.removeParameterFromUri(e,t),t,r)}static parseQueryString(e){return JSON.parse('{"'+decodeURI(e).replace(/"/g,'\\"').replace(/&/g,'","').replace(/=/g,'":"')+'"}')}static buildQueryString(e){let t="";for(let r in e)""!==t&&(t+="&"),t+=r+"="+e[r];return t}};class u{static get(e,t,r){r=u.setDefaults(r);let n=u.initializeRequest("GET",l.addParametersToUri(e,t),r),i={config:r,action:e,data:t};u.doAjaxSubmit(n,i)}static post(e,t,r){r=u.setDefaults(r);let n=u.initializeRequest("POST",e,r),i={config:r,action:e,data:t};u.doAjaxSubmit(n,i)}static doAjaxSubmit(e,t){let r=t.config;e.onload=function(){e.status>=200&&e.status<400?a.call(r.onSuccess,e):a.call(r.onError,e),a.call(r.afterSubmit,t.action,t.data,r)},a.call(r.beforeSubmit,t.action,t.data,r),void 0===t.data?e.send():(t.data=u.prepareDataForSend(t.data),e.send(t.data))}static prepareDataForSend(e){if(!(e instanceof FormData)){let t=new FormData;return Object.keys(e).forEach(r=>{t.append(r,e[r])}),t}return e}static initializeRequest(e,t,r){let n=new XMLHttpRequest;return n.open(e,t,!0),n=u.setRequestHeaders(n,r),r.hasOwnProperty("responseType")&&(n.responseType=r.responseType),n}static setRequestHeaders(e,t){return t.hasOwnProperty("headers")&&Object.keys(t.headers).forEach(r=>{e.setRequestHeader(r,t.headers[r])}),e}static setDefaults(e){return e.hasOwnProperty("headers")||(e.headers={"X-Requested-With":"XMLHttpRequest"}),e}}let c={ajax:u,array:i,dom:o,event:s,url:l,util:a};window.utilsBundle=c;r("CkLv");function d(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}window.Dropzone=r("eeMe"),Dropzone.autoDiscover=!1;var f=function(){function e(){!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e)}var t,r,n;return t=e,n=[{key:"rawurlencode",value:function(e){return e+="",encodeURIComponent(e).replace(/!/g,"%21").replace(/'/g,"%27").replace(/\(/g,"%28").replace(/\)/g,"%29").replace(/\*/g,"%2A")}},{key:"__extends",value:function(e,t){for(var r in e)e.hasOwnProperty(r)&&(t[r]=e[r]);return t}},{key:"__getField",value:function(e,t){var r=e.element.querySelectorAll('input[name="'+(void 0!==t?t+"_":"")+e.options.paramName+'"]');return void 0!==r?r[0]:"undefined"}},{key:"__registerOnClick",value:function(e,t){if(void 0===t)return!1;e.previewElement.setAttribute("onclick",t),e.previewElement.className=e.previewElement.className+" has-info"}},{key:"camelize",value:function(e){return e.replace(/[\-_](\w)/g,(function(e){return e.charAt(1).toUpperCase()}))}},{key:"__submitOnChange",value:function(t,r){if(r){if("this.form.submit()"===r)return void document.createElement("form").submit.call(e.__getField(t).form);Function(r)()}}}],(r=null)&&d(t.prototype,r),n&&d(t,n),e}(),p={init:function(){this.on("thumbnail",(function(e,t){e.width<this.options.minImageWidth||e.height<this.options.minImageHeight?"function"==typeof e.rejectDimensions&&e.rejectDimensions():"function"==typeof e.acceptDimensions&&e.acceptDimensions()})).on("removedfile",(function(e){if(e.accepted){var t=f.__getField(this,"uploaded"),r=f.__getField(this,"deleted"),n=f.__getField(this);if(void 0!==t&&void 0!==e.uuid){var i=JSON.parse(t.value);t.value=JSON.stringify(c.array.removeFromArray(e.uuid,i))}if(void 0!==n&&void 0!==e.uuid){var o=JSON.parse(n.value);n.value=JSON.stringify(c.array.removeFromArray(e.uuid,o))}if(void 0!==r&&void 0!==e.uuid){var a=JSON.parse(r.value);a.push(e.uuid),r.value=JSON.stringify(a)}this.files.length<1&&this.element.classList.remove("dz-has-files"),this.options.maxFiles>1&&f.__submitOnChange(this,this.options.onchange)}})).on("success",(function(e,r){if(void 0!==r.result){if(t.options.url=c.url.addParameterToUri(t.options.url,"ato",r.token),"undefined"===(r=r.result.data).result)return!1;var n,i=f.__getField(this,"uploaded"),o=f.__getField(this);if(r instanceof Array){for(var a=0,s=r.length;a<s;a++)if(!1!==(n=u(e,r[a]))){l(e=n,i,o),e.dataURL&&t.emit("thumbnail",e,e.dataURL),f.__registerOnClick(e,e.info);break}}else!1!==(n=u(e,r))&&(l(e=n,i,o),e.dataURL&&t.emit("thumbnail",e,e.dataURL),f.__registerOnClick(e,e.info));f.__submitOnChange(t,t.options.onchange)}else t.emit("error",e,t.options.dictResponseError.replace("{{statusCode}}",": Empty response"),r);function l(e,t,r){if(void 0!==t){var n=JSON.parse(t.value);n.push(e.uuid),t.value=JSON.stringify(n)}if(void 0!==r){var i=JSON.parse(r.value);i.push(e.uuid),r.value=JSON.stringify(i)}}function u(e,r){return r.error?(t.emit("error",e,r.error,r),!1):r.filenameOrigEncoded===f.rawurlencode(e.name)&&"undefined"!==r.uuid&&(e.serverFileName=r.filename,e.uuid=r.uuid,e.url=r.url,e.info=r.info,e.sanitizedName=r.filenameSanitized,e.previewElement.querySelector("[data-dz-name]").innerHTML=r.filenameSanitized,e)}})).on("error",(function(e,t,r){var n=e.previewElement.parentNode.querySelectorAll(".dz-error-show");if(n)for(var i=0,o=n.length;i<o;i++){n[i].classList.remove("dz-error-show")}e.previewElement.classList.remove("dz-success"),e.previewElement.classList.add("dz-error-show"),e.previewElement.addEventListener("mouseleave",(function(){this.classList.remove("dz-error-show")}))})).on("sending",(function(e,t,r){var n,i=f.__getField(this);if(void 0!==i&&(n=i.form),void 0!==n){r.append("action",this.options.uploadAction),r.append("requestToken",this.options.requestToken),r.append("FORM_SUBMIT",n.id),r.append("field",this.options.paramName);for(var o=n.querySelectorAll("input[name]:not([disabled]), textarea[name]:not([disabled]), select[name]:not([disabled]), button[name]:not([disabled])"),a=0,s=o.length;a<s;a++){var l=o[a];r.append(l.name,l.value)}}})).on("addedfile",(function(e){this.files.length>0&&this.element.classList.add("dz-has-files")}));var e=f.__getField(this,"formattedInitial"),t=this;if(void 0!==e&&(e=e.value),void 0!==e&&""!==e){for(var r=JSON.parse(e),n=0;n<r.length;n++){var i=r[n];i.accepted=!0,this.files.push(i),this.emit("addedfile",i),i.dataURL&&this.emit("thumbnail",i,i.dataURL),f.__registerOnClick(i,i.info),this.emit("complete",i)}this.files.length>0&&this.element.classList.add("dz-has-files")}}},h={init:function(){this.registerFields()},registerFields:function(){var e=document.querySelectorAll(".multifileupload");function t(e,t,r){return e.split(t).join(r)}for(var r=0,n=e.length;r<n;r++){var i=e[r];if(void 0===i.dropzone){var o=i.attributes,a=o.length,s=i.dataset;if(void 0===s)for(s={};a--;){if(/^data-.*/.test(o[a].name))s[f.camelize(o[a].name.replace("data-",""))]=o[a].value}for(var l=["dictFileTooBig","dictResponseError"],u=0;u<l.length;u++)s[l[u]]=t(s[l[u]],"{.{","{{"),s[l[u]]=t(s[l[u]],"}.}","}}");var d=f.__extends(s,p);if(d.url=location.href,c.util.isTruthy(history.state)&&c.util.isTruthy(history.state.url)&&(d.url=history.state.url),d.uploadActionParams){var h=c.url.parseQueryString(d.uploadActionParams);d.url=c.url.addParametersToUri(d.url,h)}new Dropzone(i,d)}}}};document.addEventListener("DOMContentLoaded",(function(){h.init(),window.jQuery&&window.jQuery(document).ajaxComplete((function(){h.init()})),window.MooTools&&window.addEvent("ajax_change",(function(){h.init()})),document.addEventListener("formhybrid_ajax_complete",(function(){h.init()}))}))}});