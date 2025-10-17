import React, { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { toast, Toaster } from "@/components/ui/toast";
import { __ } from "@wordpress/i18n";
import { Loader2, Save, Shield, Users, Globe, Trash2, AlertTriangle } from "lucide-react";

const ResetConnectionButton = () => {
  const [resetting, setResetting] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);

  const handleResetConnection = async () => {
    if (!showConfirmation) {
      setShowConfirmation(true);
      return;
    }

    try {
      setResetting(true);
      const response = await fetch(window.sureFeedbackAdmin?.rest_url + 'surefeedback/v1/connection/reset', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.sureFeedbackAdmin?.rest_nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          toast.success(__('Site connection reset successfully! All SureFeedback data has been cleared.', 'surefeedback'));
          // Reload the page after a short delay to refresh the UI
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        } else {
          throw new Error(data.message || 'Reset failed');
        }
      } else {
        throw new Error('Reset request failed');
      }
    } catch (error) {
      toast.error(__('Failed to reset site connection', 'surefeedback'));
    } finally {
      setResetting(false);
      setShowConfirmation(false);
    }
  };

  const handleCancel = () => {
    setShowConfirmation(false);
  };

  if (showConfirmation) {
    return (
      <div className="border border-red-300 bg-red-50 p-4 rounded-lg">
        <div className="flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-600 mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <h4 className="font-semibold text-red-800 mb-2">
              {__("Are you absolutely sure?", "surefeedback")}
            </h4>
            <p className="text-sm text-red-700 mb-4">
              {__("This will permanently delete all SureFeedback data including connection settings, user permissions, and white label settings. This action cannot be undone.", "surefeedback")}
            </p>
            <div className="flex gap-3">
              <Button
                variant="outline"
                size="sm"
                onClick={handleCancel}
                disabled={resetting}
              >
                {__("Cancel", "surefeedback")}
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={handleResetConnection}
                disabled={resetting}
              >
                {resetting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    {__("Resetting...", "surefeedback")}
                  </>
                ) : (
                  <>
                    <Trash2 className="mr-2 h-4 w-4" />
                    {__("Yes, reset everything", "surefeedback")}
                  </>
                )}
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex items-center justify-between p-4 border border-red-200 bg-red-50 rounded-lg">
      <div>
        <h4 className="font-semibold text-red-800 mb-1">
          {__("Reset Site Connection", "surefeedback")}
        </h4>
        <p className="text-sm text-red-600">
          {__("Permanently delete all SureFeedback data and disconnect from the parent site.", "surefeedback")}
        </p>
      </div>
      <Button
        variant="destructive"
        size="sm"
        onClick={handleResetConnection}
        disabled={resetting}
      >
        <Trash2 className="mr-2 h-4 w-4" />
        {__("Reset Connection", "surefeedback")}
      </Button>
    </div>
  );
};

const GeneralSettings = () => {
  const [settings, setSettings] = useState({
    surefeedback_role_can_comment: [],
    surefeedback_guest_comments_enabled: false,
    surefeedback_admin: false
  });
  const [availableRoles, setAvailableRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // Load settings on component mount
  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const response = await fetch(window.sureFeedbackAdmin?.rest_url + 'surefeedback/v1/settings', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.sureFeedbackAdmin?.rest_nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setSettings({
            surefeedback_role_can_comment: data.data.general?.surefeedback_role_can_comment || [],
            surefeedback_guest_comments_enabled: data.data.general?.surefeedback_guest_comments_enabled || false,
            surefeedback_admin: data.data.general?.surefeedback_admin || false
          });
          setAvailableRoles(data.data.availableRoles || []);
        }
      }
    } catch (error) {
      toast.error(__('Failed to load settings', 'surefeedback'));
    } finally {
      setLoading(false);
    }
  };

  const saveGeneralSettings = async (updatedSettings = null) => {
    try {
      setSaving(true);
      const dataToSave = updatedSettings || settings;

      const response = await fetch(window.sureFeedbackAdmin?.rest_url + 'surefeedback/v1/settings/general', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.sureFeedbackAdmin?.rest_nonce,
        },
        body: JSON.stringify(dataToSave),
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          toast.success(__('Settings saved successfully!', 'surefeedback'));
          return true;
        }
      }
      throw new Error('Save failed');
    } catch (error) {
      toast.error(__('Failed to save settings', 'surefeedback'));
      return false;
    } finally {
      setSaving(false);
    }
  };

  const handleRoleChange = async (roleName, selected) => {
    const updatedRoles = selected
      ? [...settings.surefeedback_role_can_comment, roleName]
      : settings.surefeedback_role_can_comment.filter(role => role !== roleName);

    const updatedSettings = {
      ...settings,
      surefeedback_role_can_comment: updatedRoles
    };

    setSettings(updatedSettings);
    await saveGeneralSettings(updatedSettings);
  };

  const handleGuestCommentsChange = async (checked) => {
    const updatedSettings = {
      ...settings,
      surefeedback_guest_comments_enabled: checked
    };

    setSettings(updatedSettings);
    await saveGeneralSettings(updatedSettings);
  };

  const handleAdminCommentsChange = async (checked) => {
    const updatedSettings = {
      ...settings,
      surefeedback_admin: checked
    };

    setSettings(updatedSettings);
    await saveGeneralSettings(updatedSettings);
  };

  const handleSaveChanges = async () => {
    await saveGeneralSettings();
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        <span className="ml-3 text-sm text-muted-foreground">
          {__('Loading settings...', 'surefeedback')}
        </span>
      </div>
    );
  }

  return (
    <>
      <div className="max-w-7xl mx-auto pt-8 px-6 pb-8 space-y-6">
        {/* Page Header */}
        <div className="space-y-1">
          <h1 className="text-3xl font-bold text-foreground">
            {__("General Settings", "surefeedback")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {__("Manage permissions and access controls for your SureFeedback installation.", "surefeedback")}
          </p>
        </div>

        <Separator />

        {/* User Permissions Card */}
        <Card className="shadow-sm">
          <CardHeader className="pb-4">
            <div className="flex items-center gap-3">
              <Shield className="h-5 w-5 text-muted-foreground" />
              <div>
                <CardTitle className="text-lg font-semibold">
                  {__("User Permissions", "surefeedback")}
                </CardTitle>
                <CardDescription className="mt-0.5">
                  {__("Allow user roles to view comments on your site without access token.", "surefeedback")}
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {availableRoles.map((role) => (
                <div key={role.name} className="flex items-center space-x-2">
                  <Switch
                    id={`role-${role.name}`}
                    checked={settings.surefeedback_role_can_comment?.includes(role.name) || false}
                    onCheckedChange={(checked) => handleRoleChange(role.name, checked)}
                  />
                  <Label
                    htmlFor={`role-${role.name}`}
                    className="text-sm font-medium cursor-pointer"
                  >
                    {role.label}
                  </Label>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Guest Comments Card */}
        <Card className="shadow-sm">
          <CardContent className="p-5">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-start gap-3 flex-1">
                <Globe className="h-5 w-5 text-muted-foreground mt-0.5 flex-shrink-0" />
                <div className="space-y-0.5">
                  <Label className="text-base font-semibold text-foreground">
                    {__("Allow Site Visitors", "surefeedback")}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {__("Allow the site visitors to view and add comments on your site without access token.", "surefeedback")}
                  </p>
                </div>
              </div>
              <Switch
                checked={settings.surefeedback_guest_comments_enabled || false}
                onCheckedChange={handleGuestCommentsChange}
                className="flex-shrink-0"
              />
            </div>
          </CardContent>
        </Card>

        {/* Dashboard Commenting Card */}
        <Card className="shadow-sm">
          <CardContent className="p-5">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-start gap-3 flex-1">
                <Users className="h-5 w-5 text-muted-foreground mt-0.5 flex-shrink-0" />
                <div className="space-y-0.5">
                  <Label className="text-base font-semibold text-foreground">
                    {__("Dashboard Commenting", "surefeedback")}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {__("Allow commenting in your site's WordPress dashboard area.", "surefeedback")}
                  </p>
                </div>
              </div>
              <Switch
                checked={settings.surefeedback_admin || false}
                onCheckedChange={handleAdminCommentsChange}
                className="flex-shrink-0"
              />
            </div>
          </CardContent>
        </Card>

        {/* Save Button */}
        <div className="flex justify-end pt-2">
          <Button
            size="lg"
            onClick={handleSaveChanges}
            disabled={saving || loading}
            className="min-w-[150px]"
          >
            {saving ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {__("Saving...", "surefeedback")}
              </>
            ) : (
              <>
                <Save className="mr-2 h-4 w-4" />
                {__("Save Changes", "surefeedback")}
              </>
            )}
          </Button>
        </div>

        {/* Danger Zone */}
        <Separator className="my-6" />
        <Card className="shadow-sm border-red-200">
          <CardHeader className="pb-4">
            <div className="flex items-center gap-3">
              <Shield className="h-5 w-5 text-red-500" />
              <div>
                <CardTitle className="text-lg font-semibold text-red-700">
                  {__("Danger Zone", "surefeedback")}
                </CardTitle>
                <CardDescription className="mt-0.5">
                  {__("Irreversible actions that will permanently delete data.", "surefeedback")}
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <ResetConnectionButton />
          </CardContent>
        </Card>
      </div>

      <Toaster
        position="top-right"
        toastOptions={{
          duration: 3000,
        }}
      />
    </>
  );
};

export default GeneralSettings;
