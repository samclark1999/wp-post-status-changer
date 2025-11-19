(function ($) {
  "use strict";

  $(document).ready(function () {
    // Show/hide password field based on status selection
    $("#status").on("change", function () {
      if ($(this).val() === "password") {
        $("#password-container").show();
      } else {
        $("#password-container").hide();
      }
    });

    // Handle form submission
    $("#psc-form").on("submit", function (e) {
      e.preventDefault();

      // Show spinner
      $("#psc-spinner").addClass("is-active");
      $("#psc-submit").prop("disabled", true);
      $("#psc-results").hide();

      var formData = new FormData(this);
      formData.append("action", "psc_process_status_change");
      formData.append("nonce", pscData.nonce);

      $.ajax({
        url: pscData.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          $("#psc-spinner").removeClass("is-active");
          $("#psc-submit").prop("disabled", false);

          if (response.success) {
            var results = response.data;
            var isDryRun = results.dry_run;

            var resultHtml =
              '<div class="psc-alert ' +
              (isDryRun ? "psc-alert-info" : "psc-alert-success") +
              '">';

            if (isDryRun) {
              resultHtml +=
                "<p><strong>Dry Run Completed - No Changes Applied</strong></p>";
            } else {
              resultHtml += "<p><strong>" + pscData.success + "</strong></p>";
            }

            resultHtml +=
              "<p>" +
              "Total entries: " +
              results.total +
              "<br>" +
              (isDryRun ? "Would update: " : "Successfully updated: ") +
              results.successful +
              "<br>" +
              "Already had target status: " +
              results.already_status +
              "<br>" +
              "Failed to update: " +
              results.failed;

            if (results.batch_size) {
              resultHtml +=
                "<br>Processed in batches of: " + results.batch_size + " posts";
            }

            resultHtml += "</p>";

            // Display failed entries with reasons if available
            if (
              results.failed > 0 &&
              results.failed_entries &&
              results.failed_entries.length > 0
            ) {
              resultHtml +=
                '<div class="psc-failed-details">' +
                "<p><strong>Failed entries:</strong></p>" +
                '<table class="widefat striped">' +
                "<thead><tr><th>Entry</th><th>Reason</th></tr></thead>" +
                "<tbody>";

              for (
                var i = 0;
                i < Math.min(results.failed_entries.length, 20);
                i++
              ) {
                var entry = results.failed_entries[i];
                resultHtml +=
                  "<tr>" +
                  "<td>" +
                  entry.slug +
                  "</td>" +
                  "<td>" +
                  entry.reason +
                  "</td>" +
                  "</tr>";
              }

              if (results.failed_entries.length > 20) {
                resultHtml +=
                  '<tr><td colspan="2">... and ' +
                  (results.failed_entries.length - 20) +
                  " more failures</td></tr>";
              }

              resultHtml += "</tbody></table></div>";
            }

            // Display posts that would be changed in dry run mode
            if (
              isDryRun &&
              results.would_change &&
              results.would_change.length > 0
            ) {
              resultHtml +=
                "<p><strong>Posts that would be updated:</strong></p>" +
                '<table class="widefat striped">' +
                "<thead><tr>" +
                "<th>ID</th><th>Title</th><th>Slug</th><th>Current Status</th><th>New Status</th>" +
                "</tr></thead><tbody>";

              for (
                var i = 0;
                i < Math.min(results.would_change.length, 50);
                i++
              ) {
                var post = results.would_change[i];
                resultHtml +=
                  "<tr>" +
                  "<td>" +
                  post.id +
                  "</td>" +
                  "<td>" +
                  post.title +
                  "</td>" +
                  "<td>" +
                  post.slug +
                  "</td>" +
                  "<td>" +
                  post.current_status +
                  "</td>" +
                  "<td>" +
                  post.new_status +
                  "</td>" +
                  "</tr>";
              }

              if (results.would_change.length > 50) {
                resultHtml +=
                  '<tr><td colspan="5">... and ' +
                  (results.would_change.length - 50) +
                  " more posts would be updated</td></tr>";
              }

              resultHtml += "</tbody></table>";
            }

            resultHtml += "</div>";

            $("#psc-results-content").html(resultHtml);
            $("#psc-results").show();
          } else {
            var errorHtml =
              '<div class="psc-alert psc-alert-error">' +
              "<p><strong>Error:</strong> " +
              response.data.message +
              "</p>";

            if (response.data.error_code) {
              errorHtml +=
                "<p>Error code: " + response.data.error_code + "</p>";
            }

            errorHtml += "</div>";

            $("#psc-results-content").html(errorHtml);
            $("#psc-results").show();
          }
        },
        error: function (xhr, status, error) {
          $("#psc-spinner").removeClass("is-active");
          $("#psc-submit").prop("disabled", false);

          var errorMessage = pscData.error;
          var errorCode = "";

          if (xhr.status) {
            errorCode = xhr.status;
            if (xhr.status === 502) {
              errorMessage =
                "Bad Gateway Error (502). The server was unable to complete the request. This often happens when processing too many posts at once.";
            } else if (xhr.status === 504) {
              errorMessage =
                "Gateway Timeout (504). The server took too long to respond. Try reducing the batch size.";
            } else if (xhr.status === 500) {
              errorMessage =
                "Internal Server Error (500). The server encountered an error. Check your server error logs for details.";
            }
          }

          var errorHtml =
            '<div class="psc-alert psc-alert-error">' +
            "<p><strong>Error " +
            (errorCode ? "(" + errorCode + ")" : "") +
            ":</strong> " +
            errorMessage +
            "</p>" +
            "<p>Try reducing the batch size or processing fewer posts at once.</p>" +
            "</div>";

          $("#psc-results-content").html(errorHtml);
          $("#psc-results").show();
        },
      });
    });

    $("#psc_action").on("change", function () {
      if ($(this).val() === "change_status") {
        $("#status-group").show();
      } else {
        $("#status-group").hide();
      }
    });

    $("#psc_mode").on("change", function () {
      if ($(this).val() === "csv") {
        $("#csv-upload-group").show();
        $("#regex-group").hide();
        $("#csv_file").prop("required", true);
        $("#psc_regex").prop("required", false);
      } else {
        $("#csv-upload-group").hide();
        $("#regex-group").show();
        $("#csv_file").prop("required", false);
        $("#psc_regex").prop("required", true);
      }
    });

    $("#post_type").on("change", function () {
      var val = $(this).val();
      var regexField = $("#psc_regex_field");
      if (val === "attachment") {
        // Remove Content and Excerpt if present
        regexField.find('option[value="post_content"], option[value="post_excerpt"]').remove();
      } else {
        // Ensure all options are present
        if (regexField.find('option[value="post_content"]').length === 0) {
          regexField.append('<option value="post_content">Content</option>');
        }
        if (regexField.find('option[value="post_excerpt"]').length === 0) {
          regexField.append('<option value="post_excerpt">Excerpt</option>');
        }
      }
    });

    function updateMediaTrashWarning() {
      var postType = $("#post_type").val();
      var action = $("#psc_action").val();
      if (postType === "attachment" && action === "trash") {
        $("#media-trash-warning").show();
      } else {
        $("#media-trash-warning").hide();
      }
    }

    $("#post_type, #psc_action").on("change", updateMediaTrashWarning);
    $(document).ready(updateMediaTrashWarning);
  });
})(jQuery);
