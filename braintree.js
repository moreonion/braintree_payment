parcelRequire=function(e,r,t,n){var i,o="function"==typeof parcelRequire&&parcelRequire,u="function"==typeof require&&require;function f(t,n){if(!r[t]){if(!e[t]){var i="function"==typeof parcelRequire&&parcelRequire;if(!n&&i)return i(t,!0);if(o)return o(t,!0);if(u&&"string"==typeof t)return u(t);var c=new Error("Cannot find module '"+t+"'");throw c.code="MODULE_NOT_FOUND",c}p.resolve=function(r){return e[t][1][r]||r},p.cache={};var l=r[t]=new f.Module(t);e[t][0].call(l.exports,p,l,l.exports,this)}return r[t].exports;function p(e){return f(p.resolve(e))}}f.isParcelRequire=!0,f.Module=function(e){this.id=e,this.bundle=f,this.exports={}},f.modules=e,f.cache=r,f.parent=o,f.register=function(r,t){e[r]=[function(e,r){r.exports=t},{}]};for(var c=0;c<t.length;c++)try{f(t[c])}catch(e){i||(i=e)}if(t.length){var l=f(t[t.length-1]);"object"==typeof exports&&"undefined"!=typeof module?module.exports=l:"function"==typeof define&&define.amd?define(function(){return l}):n&&(this[n]=l)}if(parcelRequire=f,i)throw i;return f}({"9E/x":[function(require,module,exports) {
"use strict";function e(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function t(e,t){for(var i=0;i<t.length;i++){var n=t[i];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function i(e,i,n){return i&&t(e.prototype,i),n&&t(e,n),e}Object.defineProperty(exports,"__esModule",{value:!0}),exports.MethodElement=void 0;var n=jQuery;function r(e,t,i){var n=t.shift();t.length>0?(void 0===e[n]&&(e[n]={}),r(e[n],t,i)):e[n]=i}var a=function(){function t(i,r){e(this,t),this.$element=i,this.settings=n.extend(r,{wrapperClasses:["form-control"]}),this.form_id=this.$element.closest("form").attr("id"),this.waitForLibrariesThenInit()}return i(t,[{key:"waitForLibrariesThenInit",value:function(){var e=this;"undefined"!=typeof braintree&&void 0!==braintree.client&&void 0!==braintree.hostedFields&&"undefined"!==braintree.threeDSecure?this.initFields():window.setTimeout(function(){e.waitForLibrariesThenInit()},100)}},{key:"getStyles",value:function(){var e,t={},i=n('<div class="form-item"><input type="text" class="default" /><input type="text" class="error" /><select><option>One</option></select></div>').hide().appendTo(this.$element);return e=window.getComputedStyle(i.find("input.default").get(0)),t.input={color:e.getPropertyValue("color"),font:e.getPropertyValue("font"),"line-height":e.getPropertyValue("line-height")},e=window.getComputedStyle(i.find("input.error").get(0)),t["input.invalid"]={color:e.getPropertyValue("color")},e=window.getComputedStyle(i.find("select").get(0)),t.select={font:e.getPropertyValue("font")},i.remove(),t}},{key:"startLoading",value:function(){this.$element.addClass("loading"),n('<div class="loading-wrapper"><div class="throbber"></div></div>').appendTo(this.$element.children(".fieldset-wrapper"))}},{key:"stopLoading",value:function(){this.$element.find(".loading-wrapper").remove(),this.$element.removeClass("loading")}},{key:"initFields",value:function(){var e=this;this.startLoading(),braintree.client.create({authorization:this.settings.payment_token}).then(function(t){e.client=t,e.$wrappers=e.$element.find(".braintree-hosted-fields-wrapper");var i={};return e.$wrappers.each(function(){var e=n(this),t=e.data("braintreeHostedFieldsField"),r={container:this},a=e.children("input, select");"SELECT"===a.get(0).tagName?(r.select=!0,e.addClass("select-input")):(r.placeholder=this.getAttribute("placeholder"),e.addClass("text-input")),i[t]=r,e.css({height:a.outerHeight(),"box-sizing":"border-box"})}),braintree.hostedFields.create({client:t,styles:e.getStyles(),fields:n.extend(!0,{},e.settings.fields,i)})}).then(function(t){return e.hostedFields=t,e.$wrappers.addClass("braintree-hosted-fields-processed"),e.$wrappers.addClass(e.settings.wrapperClasses.join(" ")),braintree.threeDSecure.create({version:2,client:e.client})}).then(function(t){e.client3ds=t,e.stopLoading()})}},{key:"setNonce",value:function(e){this.$element.find('[name$="[braintree-payment-nonce]"]').val(e)}},{key:"extraData",value:function(){var e={};return this.$element.find("[data-braintree-name]").each(function(){var t=n(this).attr("data-braintree-name").split("."),i=n(this).val();r(e,t,i)}),e}},{key:"validate",value:function(e){var t=this;n(".mo-dialog-wrapper").addClass("visible"),void 0!==Drupal.clientsideValidation&&n("#clientsidevalidation-"+this.form_id+"-errors ul").empty(),this.$wrappers.removeClass("invalid"),this.hostedFields.tokenize().then(function(e){return t.client3ds.verifyCard(n.extend({},t.extraData(),{nonce:e.nonce,bin:e.details.bin,onLookupComplete:function(e,t){t()}}))}).then(function(i){var n=i.threeDSecureInfo;n.liabilityShifted||!n.liabilityShiftPossible&&!t.settings.forceLiabilityShift?(t.setNonce(i.nonce),e.ready()):(t.errorHandler(Drupal.t("Card verification failed. Please choose another form of payment.")),e.error())}).catch(function(i){if("HOSTED_FIELDS_FIELDS_INVALID"===i.code)for(var n in i.details.invalidFields)i.details.invalidFields[n].classList.add("invalid");var r=i.message;r.length>0?t.errorHandler(r):t.errorHandler(i),e.error()})}},{key:"errorHandler",value:function(e){var t,i,r;void 0!==Drupal.clientsideValidation?(t=Drupal.settings.clientsideValidation.forms[this.form_id],i=document.createElement(t.general.wrapper),(r=document.createElement(t.general.errorElement)).className=t.general.errorClass,r.innerHTML=e,i.appendChild(r),n("#clientsidevalidation-"+this.form_id+"-errors ul").append(i).show().parent().show()):(0===n("#messages").length&&n('<div id="messages"><div class="section clearfix"></div></div>').insertAfter("#header"),n('<div class="messages error">'+e+"</div>").appendTo("#messages .clearfix"))}}]),t}();exports.MethodElement=a;
},{}],"epB2":[function(require,module,exports) {
"use strict";var e=require("./method-element"),a=jQuery;Drupal.behaviors.braintree_payment={},Drupal.behaviors.braintree_payment.attach=function(t,n){Drupal.payment_handler||(Drupal.payment_handler={}),a('input[name$="braintree-payment-nonce]"]',t).each(function(){if(document.body.contains(this)){var t=a(this).closest(".payment-method-form"),r=t.attr("data-pmid"),i=new e.MethodElement(t,n.braintree_payment["pmid_"+r]);Drupal.payment_handler[r]=function(e,a,t){i.validate(t)}}})};
},{"./method-element":"9E/x"}]},{},["epB2"], "braintree_payment")