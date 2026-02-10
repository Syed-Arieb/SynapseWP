jQuery(document).ready(function ($) {
  const $container = $(".synapsewp-chat-history");
  const $input = $("#synapsewp-chat-input");
  const $sendBtn = $("#synapsewp-send-btn");
  const $loadingOverlay = $("#synapsewp-loading-overlay");

  // ============ TAB SWITCHING ============
  $(".synapsewp-tab-btn").on("click", function () {
    const tab = $(this).data("tab");

    // Update tab buttons
    $(".synapsewp-tab-btn").removeClass("active");
    $(this).addClass("active");

    // Update tab content
    $(".synapsewp-tab-content").removeClass("active");
    $(`.synapsewp-tab-content[data-tab-content="${tab}"]`).addClass("active");
  });

  // ============ UTILITY FUNCTIONS ============

  // Auto-scroll to bottom of chat
  function scrollToBottom() {
    $container.scrollTop($container[0].scrollHeight);
  }

  // Add message to chat
  function appendMessage(text, type) {
    let msgClass = type === "user" ? "user" : "ai";
    // Sanitize and handle line breaks
    const safeText = $("<div>").text(text).html().replace(/\n/g, "<br>");
    let html = `<div class="synapsewp-message ${msgClass}">${safeText}</div>`;
    $container.append(html);
    scrollToBottom();
  }

  // Show typing indicator
  function showTyping() {
    let html = `<div class="synapsewp-message ai typing-indicator" id="typing-indicator"><span></span><span></span><span></span></div>`;
    $container.append(html);
    scrollToBottom();
  }

  function removeTyping() {
    $("#typing-indicator").remove();
  }

  // Show/hide loading spinner overlay
  function showLoading() {
    $loadingOverlay.css("display", "flex");
  }

  function hideLoading() {
    $loadingOverlay.hide();
  }

  // Show modern toast notification
  function showToast(message, type = "info") {
    const $toastContainer = $("#synapsewp-toast-container");
    const id = "toast-" + Date.now();
    const icon =
      type === "success"
        ? "yes"
        : type === "error"
          ? "warning"
          : type === "warning"
            ? "info"
            : "admin-info";

    const html = `
      <div id="${id}" class="synapsewp-toast ${type}">
        <div class="synapsewp-flex-align">
          <span class="dashicons dashicons-${icon}"></span>
          <span>${message}</span>
        </div>
        <span class="synapsewp-toast-close dashicons dashicons-no-alt"></span>
      </div>
    `;

    $toastContainer.append(html);

    // Auto-remove after 5 seconds
    const timeout = setTimeout(() => {
      removeToast(id);
    }, 5000);

    // Close button handler
    $(`#${id} .synapsewp-toast-close`).on("click", function () {
      clearTimeout(timeout);
      removeToast(id);
    });
  }

  function removeToast(id) {
    const $toast = $("#" + id);
    $toast.addClass("out");
    setTimeout(() => {
      $toast.remove();
    }, 300);
  }

  // Get selected text from editor
  function getSelectedText() {
    if (typeof wp !== "undefined" && wp.data && wp.data.select("core/editor")) {
      // Gutenberg
      const selectedBlock = wp.data
        .select("core/block-editor")
        .getSelectedBlock();
      if (selectedBlock && selectedBlock.attributes.content) {
        // Basic tag removal while preserving core text
        return selectedBlock.attributes.content.replace(/<[^>]*>/g, "");
      }
    } else if (typeof tinymce !== "undefined" && tinymce.activeEditor) {
      // Classic Editor
      return tinymce.activeEditor.selection.getContent({ format: "text" });
    }
    return "";
  }

  // Get full post content
  function getPostContent() {
    if (typeof wp !== "undefined" && wp.data && wp.data.select("core/editor")) {
      // Gutenberg
      return wp.data
        .select("core/editor")
        .getEditedPostContent()
        .replace(/<[^>]*>/g, "");
    } else if (typeof tinymce !== "undefined" && tinymce.activeEditor) {
      // Classic Editor
      return tinymce.activeEditor.getContent({ format: "text" });
    } else {
      return $("#content").val();
    }
  }

  // Insert content into editor
  function insertIntoEditor(content, replace = false) {
    if (typeof wp !== "undefined" && wp.data && wp.data.select("core/editor")) {
      // Gutenberg
      try {
        const selectedBlockClientId = wp.data
          .select("core/block-editor")
          .getSelectedBlockClientId();

        if (replace && selectedBlockClientId) {
          // Replace currently selected block
          const blocks = wp.blocks.rawHandler({ HTML: content });
          wp.data
            .dispatch("core/block-editor")
            .replaceBlocks(selectedBlockClientId, blocks);
        } else {
          // Insert as new blocks
          const blocks = wp.blocks.rawHandler({ HTML: content });
          wp.data.dispatch("core/editor").insertBlocks(blocks);
        }
      } catch (e) {
        console.error("SynapseWP: Block insertion error", e);
        // Fallback
        wp.data
          .dispatch("core/editor")
          .editPost({ content: getPostContent() + "\n\n" + content });
      }
    } else if (typeof tinymce !== "undefined" && tinymce.activeEditor) {
      // Classic Editor
      if (replace) {
        tinymce.activeEditor.selection.setContent(content);
      } else {
        tinymce.activeEditor.insertContent(content);
      }
    } else {
      // Text mode fallback
      var tempDiv = document.createElement("div");
      tempDiv.innerHTML = content;
      var textContent = tempDiv.innerText || tempDiv.textContent;
      var currentContent = $("#content").val();

      if (replace) {
        // Simple append for text mode since we don't have accurate selection easily,
        // but let's try to notify the user.
        $("#content").val(currentContent + "\n\n" + textContent);
        showToast("Text mode detected: Content appended.", "warning");
      } else {
        $("#content").val(currentContent + "\n\n" + textContent);
      }
    }
  }

  // ============ CHAT FUNCTIONALITY ============

  function sendMessage() {
    let text = $input.val().trim();
    let mode = $('input[name="synapsewp_mode"]:checked').val();

    if (!text) return;

    // UI Updates
    $input.val("");
    appendMessage(text, "user");
    showTyping();
    $input.prop("disabled", true);
    $sendBtn.prop("disabled", true);

    // Prepare History Context
    let history = [];
    $(".synapsewp-message")
      .not(".typing-indicator")
      .slice(-5)
      .each(function () {
        let role = $(this).hasClass("user") ? "user" : "model";
        history.push({ role: role, parts: [{ text: $(this).text() }] });
      });

    $.ajax({
      url: synapsewp_vars.ajax_url,
      method: "POST",
      contentType: "application/json",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", synapsewp_vars.nonce);
      },
      data: JSON.stringify({
        text: text,
        mode: mode,
        history: history,
      }),
      success: function (response) {
        removeTyping();
        $input.prop("disabled", false);
        $sendBtn.prop("disabled", false);
        $input.focus();

        if (response.data) {
          if (mode === "writer") {
            let title = response.data.title;
            let content = response.data.content;

            if (title) {
              if (
                typeof wp !== "undefined" &&
                wp.data &&
                wp.data.select("core/editor")
              ) {
                wp.data.dispatch("core/editor").editPost({ title: title });
              } else {
                $("#title").val(title).trigger("change");
              }
            }

            if (content) {
              insertIntoEditor(content);
            }
            appendMessage("(Content inserted into editor)", "ai");
          } else {
            let answer = response.data.content || response.data.generated_text;
            appendMessage(answer, "ai");
          }
        } else {
          appendMessage("Error: Empty response.", "ai");
        }
      },
      error: function (xhr) {
        removeTyping();
        $input.prop("disabled", false);
        $sendBtn.prop("disabled", false);
        let msg =
          xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : "Error communicating with AI.";
        appendMessage("Error: " + msg, "ai");
      },
    });
  }

  $sendBtn.on("click", function (e) {
    e.preventDefault();
    sendMessage();
  });

  $input.on("keypress", function (e) {
    if (e.which === 13 && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  // Template Chips
  $(".synapsewp-chip").on("click", function () {
    let prompt = $(this).data("prompt");
    $input.val(prompt).focus();
  });

  // ============ CONTENT TOOLS ============

  $(".synapsewp-tool-btn").on("click", function (e) {
    e.preventDefault();
    const action = $(this).data("action");
    const selectedText = getSelectedText();

    // Check if translate and no text selected, we'll offer to translate full post
    if (!selectedText && action !== "translate") {
      showToast(
        "Please select some text in the editor first to use this tool.",
        "warning",
      );
      return;
    }

    showLoading();

    let url, data;

    switch (action) {
      case "summarize":
        url = synapsewp_vars.summarize_url;
        data = {
          text: selectedText,
          length: $("#synapsewp-summary-length").val() || "medium",
        };
        break;

      case "paraphrase":
        url = synapsewp_vars.paraphrase_url;
        data = {
          text: selectedText,
          tone: $("#synapsewp-tone").val() || "professional",
        };
        break;

      case "improve":
        url = synapsewp_vars.improve_url;
        data = { text: selectedText };
        break;

      case "simplify":
        url = synapsewp_vars.simplify_url;
        data = { text: selectedText };
        break;

      case "translate":
        const textToTranslate = selectedText || getPostContent();
        if (!textToTranslate) {
          showToast("No content available to translate.", "error");
          hideLoading();
          return;
        }
        url = synapsewp_vars.translate_url;
        data = {
          text: textToTranslate,
          target_language: $("#synapsewp-target-language").val(),
        };
        break;

      default:
        hideLoading();
        return;
    }

    $.ajax({
      url: url,
      method: "POST",
      contentType: "application/json",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", synapsewp_vars.nonce);
      },
      data: JSON.stringify(data),
      success: function (response) {
        hideLoading();
        if (response.data && response.data.content) {
          insertIntoEditor(response.data.content, true);
          showToast("Changes applied successfully!", "success");
        } else if (response.data && response.data.result) {
          insertIntoEditor(response.data.result, true);
          showToast("Changes applied successfully!", "success");
        } else {
          showToast(
            "Error: " + (response.message || "Invalid response format."),
            "error",
          );
        }
      },
      error: function (xhr) {
        hideLoading();
        let msg = xhr.responseJSON?.message || "Error processing request.";
        showToast("Error: " + msg, "error");
      },
    });
  });

  // ============ SEO TOOLS ============

  $("#synapsewp-generate-meta").on("click", function (e) {
    e.preventDefault();
    const content = getPostContent();

    if (!content) {
      showToast("Please add some content to your post first.", "warning");
      return;
    }

    showLoading();

    $.ajax({
      url: synapsewp_vars.generate_meta_url,
      method: "POST",
      contentType: "application/json",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", synapsewp_vars.nonce);
      },
      data: JSON.stringify({
        content: content,
        title: $("#title").val() || "",
      }),
      success: function (response) {
        hideLoading();
        if (response.data && response.data.meta_description) {
          const data = response.data;

          $("#synapsewp-meta-desc").text(data.meta_description || "");

          // Keywords handling
          const $keywordsContainer = $("#synapsewp-keywords");
          $keywordsContainer.empty();
          if (Array.isArray(data.keywords)) {
            data.keywords.forEach((kw) => {
              $keywordsContainer.append(
                `<span class="synapsewp-keyword-tag">${kw}</span>`,
              );
            });
          } else if (data.keywords) {
            const kws = data.keywords.split(",");
            kws.forEach((kw) => {
              $keywordsContainer.append(
                `<span class="synapsewp-keyword-tag">${kw.trim()}</span>`,
              );
            });
          }

          // Score badge color-coding
          const score = (data.seo_score || data.score || "").toLowerCase();
          const $scoreBadge = $("#synapsewp-seo-score");
          $scoreBadge.text(data.seo_score || data.score || "Unknown");
          $scoreBadge.removeClass(
            "synapsewp-score-good synapsewp-score-average synapsewp-score-poor",
          );

          if (
            score.includes("good") ||
            score.includes("high") ||
            score.includes("excellent")
          ) {
            $scoreBadge.addClass("synapsewp-score-good");
          } else if (
            score.includes("average") ||
            score.includes("medium") ||
            score.includes("fair")
          ) {
            $scoreBadge.addClass("synapsewp-score-average");
          } else {
            $scoreBadge.addClass("synapsewp-score-poor");
          }

          // Suggestions
          const $suggestions = $("#synapsewp-seo-suggestions");
          $suggestions.empty();
          if (data.suggestions && Array.isArray(data.suggestions)) {
            data.suggestions.forEach(function (suggestion) {
              $suggestions.append(`<li>${suggestion}</li>`);
            });
          }

          $("#synapsewp-seo-results").slideDown();
        } else {
          showToast(
            "Error generating SEO meta: " +
              (response.message || "Unknown error"),
            "error",
          );
        }
      },
      error: function (xhr) {
        hideLoading();
        let msg = xhr.responseJSON?.message || "Error generating SEO meta.";
        showToast("Error: " + msg, "error");
      },
    });
  });

  // Copy meta description
  $("#synapsewp-copy-meta").on("click", function (e) {
    e.preventDefault();
    const metaText = $("#synapsewp-meta-desc").text();
    if (metaText) {
      navigator.clipboard.writeText(metaText).then(function () {
        const $btn = $(e.currentTarget);
        const originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
        setTimeout(() => $btn.html(originalHtml), 2000);
      });
    }
  });

  // ============ TEMPLATES ============

  $(".synapsewp-template-btn").on("click", function (e) {
    e.preventDefault();
    const action = $(this).data("action");
    const content = getPostContent();

    if (!content) {
      showToast("Please add some content to your post first.", "warning");
      return;
    }

    showLoading();

    $.ajax({
      url: url,
      method: "POST",
      contentType: "application/json",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", synapsewp_vars.nonce);
      },
      data: JSON.stringify({ content: content }),
      success: function (response) {
        hideLoading();
        if (response.data && (response.data.result || response.data.content)) {
          insertIntoEditor(response.data.result || response.data.content);
          showToast("Template generated successfully!", "success");
        } else {
          showToast(
            "Error: " + (response.message || "Invalid template response."),
            "error",
          );
        }
      },
      error: function (xhr) {
        hideLoading();
        let msg = xhr.responseJSON?.message || "Error generating template.";
        showToast("Error: " + msg, "error");
      },
    });
  });
});
