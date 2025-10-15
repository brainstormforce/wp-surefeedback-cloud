import React, { useState, useEffect } from "react";
import {
  Container,
  Title,
  Button,
  Switch,
  Loader,
  toast,
  Toaster,
} from "@bsf/force-ui";
import { LoaderCircle, FileText } from "lucide-react";
import { __ } from "@wordpress/i18n";
import { useSettings } from "../hooks/useSettings";

const WhiteLabel = () => {
  const {
    settings,
    loading,
    saving,
    errors,
    loadSettings,
    saveWhiteLabelSettings,
    updateWhiteLabelField,
    clearErrors,
  } = useSettings();

  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  const handleInputChange = (field, value) => {
    updateWhiteLabelField(field, value);
  };

  const handleSaveChanges = async () => {
    setIsLoading(true);
    const success = await saveWhiteLabelSettings();
    setIsLoading(false);

    if (success) {
      toast.success(
        __("White label settings saved successfully!", "surefeedback")
      );
    }
  };
  return (
    <div className="rounded-lg" style={{  width: "720px",
          marginLeft: "40px", }}>
      <div
        className="flex flex-row items-center justify-between"
        style={{
          borderRadius: "8px",
        }}
      ></div>

      <div
        style={{ borderRadius: "8px", overflow: "hidden" }}
        className="bg-background-primary"
      >
        <Container
          align="stretch"
          className="flex flex-column p-6"
          containerType="flex"
          direction="column"
        >
          <Title
            icon={null}
            iconPosition="right"
            size="sm"
            tag="h2"
            title={__("White Label", "surefeedback")}
            description={__(
              "Customize the SureFeedback interface with your own branding by labels to create a seamless client experience.",
              "surefeedback"
            )}
          />
          <Container.Item className="flex flex-col w-full">
            <div className="text-sm text-field-label font-semibold m-0">
              {__("Plugin Name", "surefeedback")}
            </div>
            <input
              name="surefeedback_plugin_name"
              type="text"
              className="w-full border border-subtle"
              value={settings.whiteLabel.surefeedback_plugin_name || ""}
              onChange={(e) =>
                handleInputChange("surefeedback_plugin_name", e.target.value)
              }
              placeholder={__("SureFeedback Client Site", "surefeedback")}
              disabled={saving}
              style={{
                height: "48px",
                borderColor: "#e0e0e0",
                outline: "none",
                boxShadow: "none",
                marginTop: "6px",
              }}
              onFocus={(e) => (e.target.style.borderColor = "#6005FF")}
              onBlur={(e) => (e.target.style.borderColor = "#e0e0e0")}
            />
          </Container.Item>
          <Container.Item className="flex flex-col w-full">
            <div className="text-sm text-field-label font-semibold m-0">
              {__("Plugin Description", "surefeedback")}
            </div>
            <textarea
              name="surefeedback_plugin_description"
              className="w-full border border-subtle resize-none"
              value={settings.whiteLabel.surefeedback_plugin_description || ""}
              onChange={(e) =>
                handleInputChange(
                  "surefeedback_plugin_description",
                  e.target.value
                )
              }
              placeholder={__(
                "Collect feedback from client websites and sync with SureFeedback parent project",
                "surefeedback"
              )}
              disabled={saving}
              rows={3}
              style={{
                height: "88px",
                borderColor: "#e0e0e0",
                outline: "none",
                boxShadow: "none",
                borderRadius: "6px",
                marginTop: "6px",
              }}
              onFocus={(e) => (e.target.style.borderColor = "#6005FF")}
              onBlur={(e) => (e.target.style.borderColor = "#e0e0e0")}
            />
          </Container.Item>
          <Container.Item className="flex flex-col w-full">
            <div className="text-sm text-field-label font-semibold m-0">
              {__("Plugin Author", "surefeedback")}
            </div>
            <input
              name="surefeedback_plugin_author"
              type="text"
              className="w-full border border-subtle"
              value={settings.whiteLabel.surefeedback_plugin_author || ""}
              onChange={(e) =>
                handleInputChange("surefeedback_plugin_author", e.target.value)
              }
              placeholder={__("Your Agency Name", "surefeedback")}
              disabled={saving}
              style={{
                height: "48px",
                borderColor: "#e0e0e0",
                outline: "none",
                boxShadow: "none",
                marginTop: "6px",
              }}
              onFocus={(e) => (e.target.style.borderColor = "#6005FF")}
              onBlur={(e) => (e.target.style.borderColor = "#e0e0e0")}
            />
          </Container.Item>
          <Container.Item className="flex flex-col w-full">
            <div className="text-sm text-field-label font-semibold m-0">
              {__("Author URL", "surefeedback")}
            </div>
            <input
              name="surefeedback_plugin_author_url"
              type="url"
              className="w-full border border-subtle"
              value={settings.whiteLabel.surefeedback_plugin_author_url || ""}
              onChange={(e) =>
                handleInputChange(
                  "surefeedback_plugin_author_url",
                  e.target.value
                )
              }
              placeholder={__("https://youragency.com", "surefeedback")}
              disabled={saving}
              style={{
                height: "48px",
                borderColor: "#e0e0e0",
                outline: "none",
                boxShadow: "none",
                marginTop: "6px",
              }}
              onFocus={(e) => (e.target.style.borderColor = "#6005FF")}
              onBlur={(e) => (e.target.style.borderColor = "#e0e0e0")}
            />
          </Container.Item>

          <Container.Item className="flex flex-col w-full">
            <div className="text-sm text-field-label font-semibold m-0">
              {__("Plugin URL", "surefeedback")}
            </div>
            <input
              name="surefeedback_plugin_link"
              type="url"
              className="w-full border border-subtle"
              value={settings.whiteLabel.surefeedback_plugin_link || ""}
              onChange={(e) =>
                handleInputChange("surefeedback_plugin_link", e.target.value)
              }
              placeholder={__(
                "https://youragency.com/feedback-solution",
                "surefeedback"
              )}
              disabled={saving}
              style={{
                height: "48px",
                borderColor: "#e0e0e0",
                outline: "none",
                boxShadow: "none",
                marginTop: "6px",
              }}
              onFocus={(e) => (e.target.style.borderColor = "#6005FF")}
              onBlur={(e) => (e.target.style.borderColor = "#e0e0e0")}
            />
          </Container.Item>

          {/* <div
            className="border border-subtle"
            style={{
              height: "2px",
              backgroundColor: "#E5E7EB",
              marginTop: "6px",
            }}
          /> */}

          {errors.whiteLabel && (
            <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-600">{errors.whiteLabel}</p>
            </div>
          )}

          <div className="">
            <Button
              type="submit"
              style={{
                backgroundColor: "#4353FF",
                marginTop: "14px",
                borderRadius: "6px",
              }}
              iconPosition="left"
              className="sticky "
              onClick={handleSaveChanges}
              disabled={saving || isLoading}
            >
              {(saving || isLoading) && (
                <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
              )}
              {__("Save Changes", "surefeedback")}
            </Button>
          </div>
        </Container>
      </div>
      <Toaster
        position="top-right"
        reverseOrder={false}
        gutter={8}
        containerStyle={{
          top: 20,
          right: 20,
          marginTop: "40px",
        }}
        toastOptions={{
          duration: 1000,
          style: {
            background: "white",
          },
          success: {
            duration: 2000,
            style: {
              color: "",
            },
            iconTheme: {
              primary: "#6005ff",
              secondary: "#fff",
            },
          },
        }}
      />
    </div>
  );
};

export default WhiteLabel;
