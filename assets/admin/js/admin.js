/**
 * Design Cart Migration
 * Code: design-cart-migration
 * Autor: Paweł Nosko — https://www.designcart.pl/pawel-nosko.html
 * Firma: Design Cart — https://www.designcart.pl/
 */

(function ($) {
  "use strict";

  var cfg = window.dccmAdmin || {};
  var i18n = cfg.i18n || {};
  var busy = false;

  function formatBytes(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes < 1024) {
      return bytes + " B";
    }
    if (bytes < 1024 * 1024) {
      return (bytes / 1024).toFixed(1) + " KB";
    }
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  function ensureOverlay() {
    var $el = $("#dccm-busy-overlay");
    if ($el.length) {
      return $el;
    }

    $el = $(
      '<div id="dccm-busy-overlay" class="dccm-busy" hidden aria-live="assertive" aria-modal="true" role="dialog">' +
        '<div class="dccm-busy__card">' +
        '<div class="dccm-busy__spinner" aria-hidden="true"></div>' +
        '<h2 class="dccm-busy__title"></h2>' +
        '<p class="dccm-busy__hint"></p>' +
        '<div class="dccm-busy__bar"><div class="dccm-busy__fill"></div></div>' +
        '<p class="dccm-busy__status"></p>' +
        '<div class="dccm-busy__actions" hidden>' +
        '<a class="dc-btn dc-btn--primary dccm-busy__download" href="#">' +
        '<i class="fa fa-download"></i> <span></span></a>' +
        '<button type="button" class="dc-btn dc-btn--secondary dccm-busy__close"></button>' +
        "</div>" +
        "</div>" +
        "</div>"
    );
    $("body").append($el);

    $el.on("click", ".dccm-busy__close", function () {
      hideBusy();
    });

    return $el;
  }

  function showBusy(title, hint) {
    busy = true;
    var $el = ensureOverlay();
    $el.removeClass("dccm-busy--done");
    $el.find(".dccm-busy__spinner").show();
    $el.find(".dccm-busy__bar").show();
    $el.find(".dccm-busy__actions").prop("hidden", true);
    $el.find(".dccm-busy__title").text(title || i18n.running || "Przetwarzanie…");
    $el.find(".dccm-busy__hint").text(hint || "");
    $el.find(".dccm-busy__fill").css("width", "0%");
    $el.find(".dccm-busy__status").text("");
    $el.prop("hidden", false).attr("aria-hidden", "false");
    $("body").addClass("dccm-busy-lock");
  }

  function updateBusy(percent, status) {
    var $el = ensureOverlay();
    var p = Math.max(0, Math.min(100, Number(percent) || 0));
    $el.find(".dccm-busy__fill").css("width", p + "%");
    if (status) {
      $el.find(".dccm-busy__status").text(status);
    }
  }

  function showBusyDone(downloadUrl, filename, statusText) {
    // Must clear busy BEFORE any download navigation / iframe load.
    busy = false;
    var $el = ensureOverlay();
    $el.addClass("dccm-busy--done");
    $el.find(".dccm-busy__spinner").hide();
    $el.find(".dccm-busy__bar").hide();
    $el.find(".dccm-busy__title").text(i18n.doneTitle || "Dump gotowy");
    $el
      .find(".dccm-busy__hint")
      .text(
        i18n.doneHint ||
          "Plik jest gotowy. Jeśli pobieranie nie wystartowało automatycznie, kliknij przycisk poniżej."
      );
    $el.find(".dccm-busy__status").text(statusText || "");
    $el
      .find(".dccm-busy__download")
      .attr("href", downloadUrl)
      .attr("download", filename || "")
      .find("span")
      .text(i18n.downloadAgain || "Pobierz plik SQL");
    $el.find(".dccm-busy__close").text(i18n.close || "Zamknij");
    $el.find(".dccm-busy__actions").prop("hidden", false);
    $el.prop("hidden", false).attr("aria-hidden", "false");
    $("body").addClass("dccm-busy-lock");
  }

  function hideBusy() {
    busy = false;
    var $el = $("#dccm-busy-overlay");
    if ($el.length) {
      $el.prop("hidden", true).attr("aria-hidden", "true");
    }
    $("body").removeClass("dccm-busy-lock");
  }

  /**
   * Start file download without leaving the admin page / triggering beforeunload.
   */
  function triggerDownload(url) {
    if (!url) {
      return;
    }

    var $frame = $("#dccm-download-frame");
    if (!$frame.length) {
      $frame = $(
        '<iframe id="dccm-download-frame" title="download" style="display:none;width:0;height:0;border:0;"></iframe>'
      );
      $("body").append($frame);
    }
    $frame.attr("src", url);
  }

  function bindTablesToolbar($root) {
    $root.on("click", "[data-dccm-tables]", function (e) {
      e.preventDefault();
      var mode = $(this).attr("data-dccm-tables");
      var $labels = $root.find(".dccm-tables-list label");
      var $boxes = $labels.find("input[type='checkbox']");

      if (mode === "all") {
        $boxes.prop("checked", true);
        return;
      }
      if (mode === "none") {
        $boxes.prop("checked", false);
        return;
      }
      if (mode === "skip-large") {
        var minBytes = parseInt($(this).attr("data-dccm-min-bytes"), 10) || 10 * 1024 * 1024;
        $labels.each(function () {
          var bytes = parseInt($(this).attr("data-dccm-bytes"), 10) || 0;
          if (bytes >= minBytes) {
            $(this).find("input[type='checkbox']").prop("checked", false);
          }
        });
      }
    });
  }

  function bindLiveConfirm($form) {
    if (!$form.length || !$form.is("[data-dccm-live-confirm]")) {
      return;
    }

    $form.on("submit", function (e) {
      if (busy) {
        e.preventDefault();
        return;
      }

      var dry = $form.find("#dccm-replace-dry").is(":checked");
      if (!dry) {
        var msg =
          i18n.confirmLive ||
          "Zamiana na żywo zmieni dane w bieżącej bazie. Zrób kopię zapasową. Kontynuować?";
        if (!window.confirm(msg)) {
          e.preventDefault();
          return;
        }
      }

      showBusy(
        i18n.replaceTitle || i18n.running || "Przetwarzanie…",
        i18n.replaceHint || ""
      );
      updateBusy(15, i18n.running || "Przetwarzanie…");
    });
  }

  function postAjax(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = cfg.nonce;
    return $.ajax({
      url: cfg.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: data,
    });
  }

  function bindAjaxExport($form) {
    if (!$form.length || !$form.is("[data-dccm-ajax-export]")) {
      return;
    }

    var $progress = $("#dccm-export-progress");
    var $fill = $("#dccm-export-fill");
    var $status = $("#dccm-export-status");
    var $submit = $("#dccm-export-submit");
    var $downloadLink = $("#dccm-export-download-link");

    function setInlineProgress(percent, text) {
      $progress.addClass("is-active").attr("aria-hidden", "false");
      $fill.css("width", Math.max(0, Math.min(100, percent)) + "%");
      $status.text(text || "");
    }

    function runBatches(token) {
      return postAjax("dccm_export_batch", { token: token }).then(function (res) {
        if (!res || !res.success) {
          var msg =
            (res && res.data && res.data.message) ||
            i18n.error ||
            "Wystąpił błąd.";
          return $.Deferred().reject(msg).promise();
        }

        var d = res.data || {};
        var percent = Number(d.percent) || 0;
        var label =
          (i18n.exportTable || "Tabela") +
          ": " +
          (d.current_table || "—") +
          " · " +
          (d.tables_done || 0) +
          "/" +
          (d.tables_total || 0) +
          " · " +
          formatBytes(d.bytes);

        setInlineProgress(percent, label);
        updateBusy(percent, label);

        if (d.complete) {
          return d;
        }

        return runBatches(token);
      });
    }

    $form.on("submit", function (e) {
      e.preventDefault();
      if (busy) {
        return;
      }

      if (!$form[0].checkValidity()) {
        $form[0].reportValidity();
        return;
      }

      var tables = [];
      $form.find(".dccm-tables-list input[type='checkbox']:checked").each(function () {
        tables.push($(this).val());
      });

      var payload = {
        search_for: $form.find("[name='search_for']").val(),
        replace_with: $form.find("[name='replace_with']").val(),
        smart_urls: $form.find("[name='smart_urls']").is(":checked") ? "1" : "",
        replace_guids: $form.find("[name='replace_guids']").is(":checked") ? "1" : "",
        tables: tables,
      };

      $submit.prop("disabled", true);
      $downloadLink.prop("hidden", true).attr("href", "#");
      showBusy(
        i18n.exportTitle || "Generowanie dumpa SQL",
        i18n.exportHint || ""
      );
      setInlineProgress(1, i18n.running || "Przetwarzanie…");
      updateBusy(1, i18n.running || "Przetwarzanie…");

      postAjax("dccm_export_start", payload)
        .then(function (res) {
          if (!res || !res.success || !res.data || !res.data.token) {
            var msg =
              (res && res.data && res.data.message) ||
              i18n.error ||
              "Wystąpił błąd.";
            return $.Deferred().reject(msg).promise();
          }
          return runBatches(res.data.token);
        })
        .then(function (done) {
          if (!done || !done.download_url) {
            return $.Deferred()
              .reject(i18n.error || "Brak linku do pobrania.")
              .promise();
          }

          var summary =
            (done.filename || "dump.sql") + " · " + formatBytes(done.bytes);
          setInlineProgress(100, (i18n.done || "Gotowe.") + " " + summary);
          $downloadLink.attr("href", done.download_url).prop("hidden", false);

          showBusyDone(done.download_url, done.filename || "", summary);
          // Start download without leaving the page (avoids beforeunload block).
          triggerDownload(done.download_url);
        })
        .fail(function (err) {
          var msg =
            typeof err === "string"
              ? err
              : (err && err.responseJSON && err.responseJSON.data && err.responseJSON.data.message) ||
                i18n.error ||
                "Wystąpił błąd.";
          hideBusy();
          setInlineProgress(0, msg);
          window.alert(msg);
        })
        .always(function () {
          $submit.prop("disabled", false);
        });
    });
  }

  $(function () {
    var $page = $(".dccm-page");
    if (!$page.length) {
      return;
    }

    bindTablesToolbar($page);
    bindLiveConfirm($("#dccm-replace-form"));
    bindAjaxExport($("#dccm-export-form"));

    $(window).on("beforeunload", function (e) {
      if (!busy) {
        return;
      }
      e.preventDefault();
      e.returnValue = "";
      return "";
    });
  });
})(jQuery);
