jQuery(document).ready(function ($) {
  const config = window.LimeFiltersCompare || {};
  const ajaxUrl = config.ajaxUrl || "";
  const action = config.action || "lf_get_compare_data";
  const maxItems =
    typeof config.maxItems === "number" && config.maxItems > 0
      ? Math.floor(config.maxItems)
      : 4;
  const labels = Object.assign(
    {
      compare: "Compare",
      view: "View Comparison",
    },
    config.labels || {}
  );
  const i18n = Object.assign(
    {
      errorLoading: "Error loading compare data.",
    },
    config.i18n || {}
  );

  let compareList = [];
  try {
    const stored = JSON.parse(localStorage.getItem("compareList"));
    if (Array.isArray(stored)) {
      compareList = stored.map((id) => id.toString());
    }
  } catch (e) {
    compareList = [];
  }

  if (maxItems > 0 && compareList.length > maxItems) {
    compareList = compareList.slice(0, maxItems);
  }

  const cardClassSetting = (config.cardClass || "").trim();
  const cardClassTokens = cardClassSetting
    .split(/\s+/)
    .map((cls) => cls.replace(/^\./, ""))
    .filter(Boolean);

  sessionStorage.removeItem("wcp_open_modal");

  function findCardElement(element) {
    if (!cardClassTokens.length) return null;
    let current = element ? element.parentElement : null;

    while (current) {
      if (
        current.classList &&
        cardClassTokens.every((className) => current.classList.contains(className))
      ) {
        return current;
      }
      current = current.parentElement;
    }

    return null;
  }

  function applyCardClassLogic() {
    if (!cardClassTokens.length) {
      document.querySelectorAll(".wcp-button-group").forEach((groupEl) => {
        groupEl.style.display = "";
        const compareEl = groupEl.querySelector(".wcp-compare-button");
        if (compareEl) {
          compareEl.style.display = "";
        }
      });
      return;
    }

    document.querySelectorAll(".wcp-button-group").forEach((groupEl) => {
      const cardEl = findCardElement(groupEl);
      if (!cardEl) {
        groupEl.style.display = "none";
        return;
      }

      const compareEl = groupEl.querySelector(".wcp-compare-button");
      const hasAddToCart = !!cardEl.querySelector(".add_to_cart_button");
      if (hasAddToCart) {
        groupEl.style.display = "none";
        if (compareEl) {
          compareEl.style.display = "none";
        }
        return;
      }

      groupEl.style.display = "";
      if (compareEl) {
        compareEl.style.display = "";
      }
    });
  }

  function saveCompareList() {
    if (maxItems > 0 && compareList.length > maxItems) {
      compareList = compareList.slice(compareList.length - maxItems);
    }
    localStorage.setItem("compareList", JSON.stringify(compareList));
    updateCompareButtons();
    updateCompareShortcodeIcon();
  }

  function updateCompareButtons() {
    $(".wcp-compare-button").each(function () {
      const id = $(this).data("product-id").toString();
      if (compareList.includes(id)) {
        $(this).text(labels.view);
      } else {
        $(this).text(labels.compare);
      }
    });
    applyCardClassLogic();
  }

  function updateCompareShortcodeIcon() {
    const el = document.querySelector(".wcp-shortcode-icon");
    if (!el) return;

    if (compareList.length > 0) {
      el.style.display = "flex";
      const countEl = el.querySelector(".wcp-count");
      if (countEl) {
        countEl.textContent = `${compareList.length}`;
      }

      el.onclick = function () {
        updateModal();
      };
    } else {
      el.style.display = "none";
    }
  }

  function updateModal() {
    if (compareList.length === 0) {
      closeModal();
      return;
    }

    if (!ajaxUrl) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      data: {
        action,
        product_ids: compareList,
      },
      success: function (response) {
        if (response.success) {
          $("#wcp-compare-table-container").html(response.data);
          $("#wcp-compare-modal").show();

          // JS Event Tracking for Compare Actions
          window.dataLayer = window.dataLayer || [];
          window.dataLayer.push({
            event: "compare_modal_open",
            product_ids: compareList,
          });


          // Delay scroll detection until DOM is fully updated
          setTimeout(function () {
            const scrollContainer = document.querySelector(".wcp-table-scroll");
            const swipeHint = document.querySelector(".wcp-swipe-hint");

            if (scrollContainer && swipeHint) {
              const hasHorizontalScroll =
                scrollContainer.scrollWidth > scrollContainer.clientWidth;
              swipeHint.style.display = hasHorizontalScroll ? "block" : "none";
            }
          }, 50);
        } else {
          alert(i18n.errorLoading);
        }
      },
      error: function () {
        alert(i18n.errorLoading);
      },
    });
  }

  function closeModal() {
    $("#wcp-compare-modal").hide();
    sessionStorage.removeItem("wcp_open_modal");
  }

  $(document).on("click", ".wcp-compare-button", function () {
    const productId = $(this).data("product-id").toString();

    if (compareList.includes(productId)) {
      sessionStorage.setItem("wcp_open_modal", "yes");
      updateModal();
      return;
    }

    if (maxItems > 0 && compareList.length >= maxItems) {
      compareList.shift();
    }

    compareList.push(productId);
    saveCompareList();
    sessionStorage.setItem("wcp_open_modal", "yes");
    if (compareList.length >= 2) {
      updateModal();
    }

    // JS Event Tracking for Compare Actions
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "compare_click",
      product_id: productId,
      compare_count: compareList.length,
    });
  });

  $(document).on("click", ".wcp-close-compare", closeModal);

  $(document).on("click", ".wcp-remove-item", function () {
    const idToRemove = $(this).data("remove-id").toString();
    compareList = compareList.filter((id) => id !== idToRemove);

    saveCompareList();
    updateModal();

    // JS Event Tracking for Compare Actions
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: "compare_remove",
      product_id: idToRemove,
      compare_count: compareList.length,
    });
  });

  $(document).on("click", "#wcp-clear-all", function () {
    compareList = [];
    saveCompareList();
    $("#wcp-compare-modal").hide();
    closeModal();
  });

  // Close modal on Escape key press
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      closeModal();
    }
  });

  // Close modal on overlay click
  $(document).on("click", ".wcp-overlay", function () {
    closeModal();
  });

  // Initialize buttons and modal on load
  updateCompareButtons();
  updateCompareShortcodeIcon();
  if (sessionStorage.getItem("wcp_open_modal") === "yes") {
    updateModal();
  }

  $(document.body).on("updated_wc_div", function () {
    updateCompareButtons();
    updateCompareShortcodeIcon();
  });
});
