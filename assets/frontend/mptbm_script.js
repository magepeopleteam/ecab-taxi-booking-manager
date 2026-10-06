(function ($) {
  "use strict";

  $(document).ready(function () {
    $(document).on(
      "change",
      "#mptbm_shopping_number, #mptbm_passenger_number",
      function () {
        // 0 (nothing selected) means "no filter" for that dropdown. Anything
        // else is a *minimum*: "3 passengers" asks for a vehicle that can
        // CARRY 3, so a 4- or 8-seater still qualifies. Matching the capacity
        // exactly instead hid every larger vehicle and routinely emptied the
        // whole result list - a 2-passenger search on a fleet of 4/6/8-seaters
        // matched nothing at all.
        let shoppingNumber = parseInt($("#mptbm_shopping_number").val()) || 0;
        let passengerNumber = parseInt($("#mptbm_passenger_number").val()) || 0;

        let elements = document.querySelectorAll("[data-mptbm-passanger]");

        elements.forEach(function (element) {
          let passengerCapacity = parseInt(element.getAttribute("data-mptbm-passanger"), 10) || 0;
          let bagCapacity = parseInt(element.getAttribute("data-mptbm-beg-count"), 10) || 0;

          let passengerMatches = passengerNumber === 0 || passengerCapacity >= passengerNumber;
          let bagMatches = shoppingNumber === 0 || bagCapacity >= shoppingNumber;

          // NOT $(element).hide()/.show() - .mptbm_transport_search_area
          // .mptbm_booking_item has "display: flex !important" (mptbm_style.css),
          // which beats the inline display:none jQuery's hide()/show() would set,
          // so the card never actually disappeared. mptbm_booking_item_hidden is
          // the class this codebase's CSS already wires up for exactly this (see
          // the !important override two rules below it, and the .mptbm-vehicle-
          // wrapper:has() rule that collapses the wrapper box too).
          //
          // display can't be transitioned though, so jumping straight to
          // mptbm_booking_item_hidden (display:none) is instant/jarring -
          // mptbm_booking_item_fading (opacity/transform, see CSS) plays the
          // actual animation, then hidden is added .22s later once it's done,
          // matching the CSS transition duration.
          let shouldHide = !(passengerMatches && bagMatches);
          if (shouldHide) {
            element.classList.add("mptbm_booking_item_fading");
            window.setTimeout(function () {
              element.classList.add("mptbm_booking_item_hidden");
            }, 220);
          } else {
            element.classList.remove("mptbm_booking_item_hidden");
            // Force a style recalc so the browser registers the still-faded
            // starting point before fading-in is removed below - dropping
            // both classes in the same frame would jump straight to the end
            // state instead of animating (nothing to transition from).
            void element.offsetWidth;
            element.classList.remove("mptbm_booking_item_fading");
          }
        });
      }
    );

    

    var mptbmTemplateExists = $(".mptbm-show-search-result").length;
    
    if (mptbmTemplateExists) {
      
      $(".mptbm_order_summary").css("display", "none");
      function getCookiesWithPrefix(prefix) {
        const cookies = document.cookie.split(";");
        const filteredCookies = cookies.filter((cookie) =>
          cookie.trim().startsWith(prefix)
        );
        return filteredCookies.map((cookie) => cookie.trim().split("=")[0]);
      }
      const cookieIds = getCookiesWithPrefix(".mptbm_booking_item_");

      function addClassFromElements() {
        $(".mptbm_booking_item").each(function () {
          const $this = $(this);
          let hasCookieId = false;
          for (let i = 0; i < cookieIds.length; i++) {
            document.cookie = `${cookieIds[i]}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
            if ($this.hasClass(cookieIds[i].substring(1))) {
              hasCookieId = true;
              break;
            }
          }
          if (!hasCookieId) {
            $this.addClass("mptbm_booking_item_hidden");
          }
        });
      }

      // Call the function to add the class
      addClassFromElements();
    }
  });
})(jQuery);
