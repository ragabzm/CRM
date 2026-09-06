/*
 * The script a website pastes in to get a chat button.
 *
 * Vanilla, tiny, and it touches nothing on the host page: no globals beyond
 * one namespaced object, no stylesheet injected into their document, no
 * framework, no polyfills. A support widget that broke somebody's homepage
 * would be a support problem we caused.
 *
 * Everything real happens INSIDE THE IFRAME. The host page never sees the
 * conversation, never sees the token, and cannot read either — an iframe on a
 * different origin is a boundary the browser enforces, which is the only kind
 * worth relying on when the page around it belongs to somebody else.
 *
 *   <script src="https://support.example.com/widget/loader.js"
 *           data-base="https://support.example.com" defer></script>
 */
(function () {
  "use strict";

  if (window.__ragabChatLoaded) {
    // Pasted twice, which happens. Two launchers is worse than one.
    return;
  }

  window.__ragabChatLoaded = true;

  var script = document.currentScript;
  var base = (script && script.getAttribute("data-base")) || "";
  var dir = document.documentElement.getAttribute("dir") === "rtl" ? "rtl" : "ltr";
  var lang =
    (document.documentElement.getAttribute("lang") || "en").slice(0, 2) === "ar" ? "ar" : "en";

  var label = lang === "ar" ? "المحادثة المباشرة" : "Live chat";

  var launcher = document.createElement("button");
  launcher.type = "button";
  launcher.setAttribute("aria-expanded", "false");
  launcher.textContent = label;

  /*
   * Styles set inline rather than through a stylesheet.
   *
   * A <style> element injected into the host document is a rule that can hit
   * their elements too, and theirs can hit ours. Inline styles on two elements
   * we own cannot leak either way.
   *
   * `inset-inline-end` rather than `right`, so the launcher sits on the
   * correct side of an Arabic page without the widget having to know which
   * side that is.
   */
  launcher.style.cssText = [
    "position:fixed",
    "inset-block-end:16px",
    "inset-inline-end:16px",
    "z-index:2147483000",
    "min-block-size:44px",
    "padding:12px 18px",
    "border:1px solid #1c2333",
    "border-radius:999px",
    "background:#1c2333",
    "color:#ffffff",
    "font:600 14px/1.2 system-ui,-apple-system,'Segoe UI',sans-serif",
    "cursor:pointer",
    "box-shadow:0 6px 20px rgba(16,19,32,0.22)",
  ].join(";");

  var frame = document.createElement("iframe");
  frame.title = label;
  frame.setAttribute("loading", "lazy");
  /*
   * The frame is not created with a src until it is opened.
   *
   * A widget nobody clicked should cost the host page nothing — no request, no
   * conversation row, and no cookie set on a visitor who never wanted to talk
   * to us.
   */
  frame.style.cssText = [
    "position:fixed",
    "inset-block-end:72px",
    "inset-inline-end:16px",
    "z-index:2147483000",
    // Fits a 390px phone with room either side, and never wider than the
    // viewport on one.
    "inline-size:min(380px, calc(100vw - 32px))",
    "block-size:min(560px, calc(100vh - 96px))",
    "border:0",
    "border-radius:12px",
    "background:transparent",
    "box-shadow:0 18px 48px rgba(16,19,32,0.28)",
    "display:none",
  ].join(";");

  function open() {
    if (!frame.src) {
      /*
       * `/widget/frame`, not the static file.
       *
       * The route sets `frame-ancestors` from the allow-list, and that header
       * is the half of the embedding rule a browser enforces — a site that is
       * not listed cannot render this frame at all, whatever its script says.
       *
       * The host page's own origin travels with it, because from inside the
       * iframe the server cannot see which site embedded us.
       */
      frame.src =
        base +
        "/widget/frame?dir=" +
        dir +
        "&lang=" +
        lang +
        "&host=" +
        encodeURIComponent(location.origin);
    }

    frame.style.display = "block";
    launcher.setAttribute("aria-expanded", "true");
    frame.focus();
  }

  function close() {
    frame.style.display = "none";
    launcher.setAttribute("aria-expanded", "false");
    launcher.focus();
  }

  launcher.addEventListener("click", function () {
    if (frame.style.display === "block") {
      close();
    } else {
      open();
    }
  });

  /*
   * The only thing the frame is allowed to say to the page, and the only
   * thing the page listens for.
   *
   * The origin is checked because `message` events arrive from every frame on
   * the page — including ones the host embedded from somebody else — and a
   * listener that skips the check is a listener any of them can drive.
   */
  window.addEventListener("message", function (event) {
    if (base && event.origin !== new URL(base, location.href).origin) {
      return;
    }

    if (event.data && event.data.type === "ragab-chat:close") {
      close();
    }
  });

  document.body.appendChild(launcher);
  document.body.appendChild(frame);
})();
