/**
 * Slide-out folder drawer on the template-folder archive.
 *
 * - Toggle button (#toggleFolderDrawer) in the page header opens/closes it.
 * - Clicking the backdrop or pressing ESC closes it.
 * - Open/closed state is persisted in localStorage so the user's last
 *   preference sticks across reloads.
 * - Handlers are delegated to document so they survive $.refreshUIelement()
 *   swapping .folderList contents.
 */

jQuery(document).ready(function ($) {
	var STORAGE_KEY = "idwiz_folder_drawer_open";
	var $drawer = $("#folderDrawer");
	var $backdrop = $(".folder-drawer-backdrop");
	var $toggle = $("#toggleFolderDrawer");

	if (!$drawer.length) {
		return;
	}

	function openDrawer() {
		$drawer.addClass("is-open").attr("aria-hidden", "false");
		$backdrop.addClass("is-open").attr("aria-hidden", "false");
		$toggle.attr("aria-expanded", "true");
		$("body").addClass("folder-drawer-open");
		try {
			localStorage.setItem(STORAGE_KEY, "1");
		} catch (e) {}
	}

	function closeDrawer() {
		$drawer.removeClass("is-open").attr("aria-hidden", "true");
		$backdrop.removeClass("is-open").attr("aria-hidden", "true");
		$toggle.attr("aria-expanded", "false");
		$("body").removeClass("folder-drawer-open");
		try {
			localStorage.setItem(STORAGE_KEY, "0");
		} catch (e) {}
	}

	function isOpen() {
		return $drawer.hasClass("is-open");
	}

	$(document).on("click", "#toggleFolderDrawer", function (e) {
		e.preventDefault();
		if (isOpen()) {
			closeDrawer();
		} else {
			openDrawer();
		}
	});

	$(document).on("click", ".folder-drawer-backdrop, .folder-drawer-close", function (e) {
		e.preventDefault();
		closeDrawer();
	});

	// Clicking a folder/template link inside the drawer closes it (so the
	// persisted state also flips to closed for the next page load).
	$(document).on("click", "#folderDrawer .folderList a", function () {
		closeDrawer();
	});

	$(document).on("keydown", function (e) {
		if (e.key === "Escape" && isOpen()) {
			closeDrawer();
		}
	});

	// Restore persisted state on load. Skip the entry animation so the
	// drawer doesn't flash in.
	var persisted;
	try {
		persisted = localStorage.getItem(STORAGE_KEY);
	} catch (e) {
		persisted = null;
	}
	if (persisted === "1") {
		$drawer.css("transition", "none");
		$backdrop.css("transition", "none");
		openDrawer();
		// Force reflow, then restore transitions.
		$drawer[0].offsetHeight;
		$drawer.css("transition", "");
		$backdrop.css("transition", "");
	}
});
