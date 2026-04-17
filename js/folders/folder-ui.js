/**
 * Folder UI: live sidebar folder-tree expand/collapse and the in-place
 * live template search in the archive toolbar.
 *
 * This file owns setup and re-setup of those widgets. It listens for the
 * custom `idwiz:uiRefreshed` event that $.refreshUIelement() dispatches
 * after it swaps DOM content, so whenever the folder sidebar or the
 * template table is re-rendered the tree state comes back wired up
 * correctly.
 */

jQuery(document).ready(function ($) {
	if ($(".folderList").length > 0) {
		setupCategoriesView();
	}
	initialize_template_live_search();

	// Show/hide sub-category folders in the sidebar. Delegated so the
	// handler survives $.refreshUIelement swaps of .folderList.
	$(document).on("click", ".showHideSubs", function (e) {
		e.stopPropagation();

		var subCategories = $(this).siblings(".sub-categories");
		var isSubGroupOpen = subCategories.is(":visible");

		if (isSubGroupOpen) {
			subCategories.hide();
			$(this).removeClass("fa-angle-up").addClass("fa-angle-down");
		} else {
			subCategories.show();
			$(this).removeClass("fa-angle-down").addClass("fa-angle-up");
		}
	});

	// Re-apply widget setup after any refreshUIelement swap.
	$(document).on("idwiz:uiRefreshed", function (e, selector) {
		if ($(".folderList").length > 0) {
			setupCategoriesView();
		}
	});
});

// Global scope so other scripts (builder-v2/template editor, etc.) can call
// these without caring about load order.

function setupCategoriesView() {
	jQuery(".sub-categories").hide();
	jQuery(".showHideSubs").removeClass("fa-angle-up").addClass("fa-angle-down");

	// Auto-open the first top-level folder.
	jQuery(".cat-item").first().addClass("open").children(".sub-categories").show();
	jQuery(".cat-item.open").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");

	// Ensure the ancestors of the currently-selected folder are expanded.
	jQuery(".current-cat").parents(".cat-item").addClass("open").children(".sub-categories").show();
	jQuery(".current-cat, .current-cat").parents(".cat-item").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");

	// And the current folder's own children.
	jQuery(".current-cat").children(".sub-categories").show();
	jQuery(".current-cat").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");
}

// In-place live template search. Typing in #live-template-search debounces
// an AJAX-ish reload of the results region (.templateTable-results) and
// keeps the URL in sync via history.replaceState so the state is
// bookmarkable and reload-safe.
function initialize_template_live_search() {
	var $ = jQuery;
	var $form = $("#search-templates-form");
	var $input = $("#live-template-search");
	var $results = $(".templateTable-results");
	if (!$form.length || !$input.length || !$results.length) {
		return;
	}

	var searchBase = ($form.attr("data-search-base") || "").replace(/\/?$/, "/");
	var homeUrl = $form.attr("data-home-url") || searchBase;
	var debounceMs = 250;
	var timer = null;
	var lastRequestedUrl = null;

	function buildUrl(term) {
		var trimmed = (term || "").trim();
		if (!trimmed) {
			return homeUrl;
		}
		return searchBase + encodeURIComponent(trimmed) + "/";
	}

	function runSearch(term) {
		var url = buildUrl(term);
		if (url === lastRequestedUrl) {
			return;
		}
		lastRequestedUrl = url;

		if (window.history && window.history.replaceState) {
			window.history.replaceState({}, "", url);
		}

		$results.addClass("is-loading");
		var container = $("<div>").load(url + " .templateTable-results > *", function () {
			$(".templateTable-results").html(container.contents()).removeClass("is-loading");
			$(document).trigger("idwiz:uiRefreshed", [".templateTable-results"]);
		});
	}

	$form.on("submit", function (e) {
		e.preventDefault();
		runSearch($input.val());
	});

	// Submit-on-enter still works via the submit handler above, but most
	// users won't need it — typing fires the search.
	$input.on("input", function () {
		var term = $input.val();
		if (timer) {
			clearTimeout(timer);
		}
		timer = setTimeout(function () {
			runSearch(term);
		}, debounceMs);
	});

	// Clicking the native "X" in <input type="search"> fires an 'input' event
	// with an empty value, which routes to homeUrl. Nothing extra to do.
}
