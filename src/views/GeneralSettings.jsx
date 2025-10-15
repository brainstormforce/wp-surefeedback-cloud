import React, { useState, useEffect } from "react";
import { Button, Title, Container, Switch, toast, Toaster } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { LoaderCircle } from "lucide-react";
import { useSettings } from '../hooks/useSettings';

const GeneralSettings = () => {
  const {
    settings,
    availableRoles,
    loading,
    saving,
    errors,
    loadSettings,
    saveGeneralSettings,
    updateRoleSelection,
    updateGuestComments,
    updateAdminComments,
    clearErrors
  } = useSettings();

  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (availableRoles.length === 0) {
      loadSettings();
    }
  }, [availableRoles.length, loadSettings]);

  const handleRoleChange = async (roleName, selected) => {
    updateRoleSelection(roleName, selected);
    // Auto-save the setting immediately
    await saveGeneralSettings();
  };

  const handleGuestCommentsChange = async (checked) => {
    updateGuestComments(checked);
    // Auto-save the setting immediately
    await saveGeneralSettings();
  };

  const handleAdminCommentsChange = async (checked) => {
    updateAdminComments(checked);
    // Auto-save the setting immediately
    await saveGeneralSettings();
  };

  const handleSaveChanges = async () => {
    setIsLoading(true);
    const success = await saveGeneralSettings();
    setIsLoading(false);
    
    if (success) {
      toast.success(__('Settings saved successfully!', 'surefeedback'));
    }
  };

  return (
    <>
      <div
        className="box-border bg-background-primary p-6"
        style={{
          marginTop: "24px",
          borderRadius: "8px",
          width: "720px",
          marginLeft: '40px',
        }}
      >
           <Title
        icon={null}
        iconPosition="right"
        size="sm"
        tag="h2"
        title={__("Permission Management", "surefeedback")}
        description={__(
          "Control what each user role can do in SureFeedback.",
          "surefeedback"
        )}
      />
        <div className="flex flex-col mt-6 p-4 shadow-sm" style={{ border: "1px solid #E5E7EB", borderRadius: "6px",  }}>
          <Title
            // description={__('Select user roles that can access debug options. Admins are always enabled', 'surefeedback')}
            icon={null}
            iconPosition="right"
            size="xs"
            tag="h2"
            title={__("User Permissions", "surefeedback")}
              description={__(
          "Allow user roles to view comments on your site without access token.",
          "surefeedback"
        )}
          />
          <div
            style={{ marginTop: "15px" }}
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-2 gap-x-4"
          >
            {availableRoles.map((role) => (
              <label key={role.name} className="flex items-center space-x-2 text-base text-black font-medium">
                <input
                  type="checkbox"
                  className="role-checkbox uavc-remove-ring"
                  checked={settings.general.surefeedback_role_can_comment.includes(role.name)}
                  onChange={(e) => handleRoleChange(role.name, e.target.checked)}
                />
                <span className="text-sm">{role.label}</span>
              </label>
            ))}
          </div>
        </div>
        <div style={{ border: "1px solid #E5E7EB", borderRadius: "6px", marginTop: '16px', padding: '16px' }}>
          <Container
          align="center"
          className="flex flex-col lg:flex-row"
          containerType="flex"
          direction="column"
          gap="sm"
          justify="between"
          item
        >
          <Container.Item className="shrink flex flex-col">
            <div className="text-base font-semibold m-0 mb-2">
              {__("Allow Site Visitors", "surefeedback")}
            </div>
            <div
              style={{ color: "#9CA3AF" }}
              className="text-sm font-normal m-0"
            >
              {__("Allow the site visitors to view and add comments on your site without access token.", "surefeedback")}
            </div>
          </Container.Item>
          <Container.Item
            className="shrink-0 p-2 flex space-y-6 surefeedback-remove-ring"
            alignSelf="auto"
            order="none"
            style={{ marginTop: "40px" }}
          >
            <Switch
              size="md"
              value={settings.general.surefeedback_guest_comments_enabled}
              onChange={handleGuestCommentsChange}
              className="surefeedback-remove-ring"
            />
          </Container.Item>
        </Container>
        </div>

        {/* <hr
          className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle"
          style={{
            marginTop: "34px",
            marginBottom: "34px",
            borderColor: "#E5E7EB",
          }}
        /> */}

        <div style={{ border: "1px solid #E5E7EB", borderRadius: "6px", marginTop: '16px', padding: '16px' }}>
          <Container
          align="center"
          className="flex flex-col lg:flex-row"
          containerType="flex"
          direction="column"
          gap="sm"
          justify="between"
          item
        >
          <Container.Item className="shrink flex flex-col">
            <div className="text-base font-semibold m-0 mb-2">
              {__("Dashboard Commenting", "surefeedback")}
            </div>
            <div
              style={{ color: "#9CA3AF" }}
              className="text-sm font-normal m-0"
            >
              {__("Allow commenting in your site’s Wordpress dashboard area", "surefeedback")}
            </div>
          </Container.Item>
          <Container.Item
            className="shrink-0 p-2 flex space-y-6 surefeedback-remove-ring"
            alignSelf="auto"
            order="none"
            style={{ marginTop: "40px" }}
          >
            <Switch
              size="md"
              value={settings.general.surefeedback_admin}
              onChange={handleAdminCommentsChange}
              className="surefeedback-remove-ring"
            />
          </Container.Item>
        </Container>
        </div>

        {/* <hr
          className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle"
          style={{
            marginTop: "34px",
            marginBottom: "34px",
            borderColor: "#E5E7EB",
          }}
        /> */}

        {errors.general && (
          <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <p className="text-sm text-red-600">{errors.general}</p>
          </div>
        )}

       <div className="">
         <Button
          type="submit"
          style={{ backgroundColor: "#4353FF", marginTop: "30px", borderRadius: '6px' }}
          iconPosition="left"
          className="w-40 sticky surefeedback-remove-ring"
          onClick={handleSaveChanges}
          disabled={saving || isLoading}
        >
          {(saving || isLoading) && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
          {__("Save Changes", "surefeedback")}
        </Button>
       </div>
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
    </>
  );
};

export default GeneralSettings;
