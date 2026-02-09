jQuery(document).ready(function ($) {
  const $container = $(".synapsewp-chat-history");
  const $input = $("#synapsewp-chat-input");
  const $sendBtn = $("#synapsewp-send-btn");
  const $spinner = $(".synapsewp-spinner");

  // Auto-scroll to bottom
  function scrollToBottom() {
    $container.scrollTop($container[0].scrollHeight);
  }

  // Add Message to Chat
  function appendMessage(text, type) {
    let msgClass = type === "user" ? "user" : "ai";
    let html = `<div class="synapsewp-message ${msgClass}">${text.replace(/\n/g, "<br>")}</div>`;
    $container.append(html);
    scrollToBottom();
  }

  // Add Typing Indicator
  function showTyping() {
    let html = `<div class="synapsewp-message ai typing-indicator" id="typing-indicator"><span></span><span></span><span></span></div>`;
    $container.append(html);
    scrollToBottom();
  }

  function removeTyping() {
    $("#typing-indicator").remove();
  }

  // Handle Send
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

    // Prepare History Context (Collect last 5 messages)
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
            // Writer Mode Logic (same as before)
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
              if (
                typeof wp !== "undefined" &&
                wp.data &&
                wp.data.select("core/editor")
              ) {
                // Use rawHandler to convert HTML to native Gutenberg blocks
                var blocks = wp.blocks.rawHandler({ HTML: content });
                wp.data.dispatch("core/editor").insertBlocks(blocks);
              } else if (
                typeof tinymce !== "undefined" &&
                tinymce.activeEditor
              ) {
                // For Classic Editor (TinyMCE), insert as visual content
                tinymce.activeEditor.insertContent(content);
              } else {
                // Fallback for text mode: convert HTML to readable text
                var tempDiv = document.createElement("div");
                tempDiv.innerHTML = content;

                // Convert HTML structure to plain text with formatting
                var textContent = tempDiv.innerText || tempDiv.textContent;

                var currentContent = $("#content").val();
                $("#content").val(currentContent + "\n\n" + textContent);
              }
            }
            appendMessage("<em>(Content inserted into editor)</em>", "ai");
          } else {
            // Answer Mode
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
});
