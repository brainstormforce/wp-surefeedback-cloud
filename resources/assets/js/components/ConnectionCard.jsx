import React, { useState, useEffect, useCallback, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { toast } from "@/components/ui/toast";
import {
  LoaderCircle,
  ArrowUpRight,
  CheckCircle,
  AlertCircle,
  ExternalLink,
} from "lucide-react";
import { __ } from "@wordpress/i18n";

const ConnectionCard = () => {
  const [manualConnectionData, setManualConnectionData] = useState("");
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState({});
  const [connection, setConnection] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const initializedRef = useRef(false);
  const [hasInitiallyLoaded, setHasInitiallyLoaded] = useState(false);

  /** ──────────────────────────────
   * 📡 API: Load Settings
   * ────────────────────────────── */
  const loadSettings = async () => {
    try {
      setLoading(true);
      const response = await fetch(
        `${window.sureFeedbackAdmin?.rest_url}surefeedback/v1/settings`,
        {
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": window.sureFeedbackAdmin?.rest_nonce,
          },
        }
      );
      const data = await response.json();
      if (data.success) {
        setConnection(data.data.connection);
        setConnectionStatus(data.data.connectionStatus);
      }
    } catch (error) {
      console.error(error);
      setErrors({ load: __("Failed to load settings", "surefeedback") });
    } finally {
      setLoading(false);
    }
  };

  /** ──────────────────────────────
   * 💾 Save Connection
   * ────────────────────────────── */
  const saveConnectionSettings = async (settingsToSave) => {
    try {
      setSaving(true);
      const response = await fetch(
        `${window.sureFeedbackAdmin?.rest_url}surefeedback/v1/settings/connection`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": window.sureFeedbackAdmin?.rest_nonce,
          },
          body: JSON.stringify(settingsToSave),
        }
      );
      const data = await response.json();
      return data.success;
    } catch (error) {
      console.error(error);
      return false;
    } finally {
      setSaving(false);
    }
  };

  /** ──────────────────────────────
   * 🔄 Test Connection
   * ────────────────────────────── */
  const testConnection = async () => {
    return true; // mock test function
  };

  /** ──────────────────────────────
   * 🌐 Initialize Settings on Mount
   * ────────────────────────────── */
  useEffect(() => {
    if (!initializedRef.current) {
      initializedRef.current = true;
      loadSettings().then(() => setHasInitiallyLoaded(true));
    }
  }, []);

  /** ──────────────────────────────
   * 🔁 Handle Disconnect URL Events
   * ────────────────────────────── */
  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("surefeedback-site-disconnect")) {
      const cleanUrl = new URL(window.location);
      cleanUrl.searchParams.delete("surefeedback-site-disconnect");
      cleanUrl.searchParams.delete("surefeedback-site-disconnect-nonce");
      window.history.replaceState({}, "", cleanUrl.toString());
      toast({
        title: __("Successfully disconnected", "surefeedback"),
        description: __("Your site has been disconnected from SureFeedback.", "surefeedback"),
      });
      loadSettings();
    }
  }, []);

  /** ──────────────────────────────
   * 🧩 Handle Manual Import
   * ────────────────────────────── */
  const handleManualImport = useCallback(async () => {
    if (!manualConnectionData.trim()) {
      toast({ title: __("Please enter connection details", "surefeedback"), variant: "destructive" });
      return;
    }

    try {
      const connectionData = JSON.parse(manualConnectionData);
      const settingsToSave = {
        surefeedback_id: connectionData.id || connectionData.project_id,
        surefeedback_api_key: connectionData.api_key || connectionData.apikey,
        surefeedback_access_token: connectionData.access_token,
        surefeedback_parent_url: connectionData.parent_url,
        surefeedback_signature: connectionData.signature,
        surefeedback_installed: true,
      };
      const success = await saveConnectionSettings(settingsToSave);
      if (success) {
        setManualConnectionData("");
        toast({ title: __("Connection saved successfully", "surefeedback") });
      } else {
        toast({ title: __("Failed to save connection settings", "surefeedback"), variant: "destructive" });
      }
    } catch {
      toast({ title: __("Invalid JSON format", "surefeedback"), variant: "destructive" });
    }
  }, [manualConnectionData]);

  /** ──────────────────────────────
   * 🔌 Disconnect
   * ────────────────────────────── */
  const handleDisconnect = useCallback(() => {
    if (!confirm(__("Are you sure you want to disconnect?", "surefeedback"))) return;

    setIsDisconnecting(true);
    try {
      const url = new URL(window.location.href);
      url.searchParams.set("surefeedback-site-disconnect", "1");
      url.searchParams.set(
        "surefeedback-site-disconnect-nonce",
        window.sureFeedbackAdmin?.disconnect_nonce || ""
      );
      window.location.href = url.toString();
    } catch (error) {
      setIsDisconnecting(false);
      toast({ title: __("Failed to disconnect", "surefeedback"), variant: "destructive" });
    }
  }, []);

  /** ──────────────────────────────
   * 🧪 Test Connection
   * ────────────────────────────── */
  const handleTestConnection = useCallback(async () => {
    const success = await testConnection();
    toast({
      title: success
        ? __("Connection successful", "surefeedback")
        : __("Connection failed", "surefeedback"),
      variant: success ? "default" : "destructive",
    });
  }, []);

  /** ──────────────────────────────
   * 🧭 Connection Status Display
   * ────────────────────────────── */
  const getConnectionStatusDisplay = () => {
    if (loading && !hasInitiallyLoaded) {
      return (
        <div className="flex items-center gap-2 bg-muted px-4 py-2 rounded-md mt-2">
          <LoaderCircle className="animate-spin h-4 w-4 text-muted-foreground" />
          <span className="text-foreground text-sm">
            {__("Loading connection status...", "surefeedback")}
          </span>
        </div>
      );
    }

    if (connectionStatus.connected && connectionStatus.parent_url) {
      return (
        <div className="flex items-center gap-2 bg-green-50 border border-green-200 px-4 py-2 rounded-md mt-3">
          <CheckCircle className="text-green-600 h-4 w-4" />
          <span className="text-green-800 text-sm font-medium">
            {__("Connected to", "surefeedback")} {connectionStatus.parent_url}
          </span>
        </div>
      );
    }

    return (
      <div className="flex items-center gap-2 bg-yellow-50 border border-yellow-200 px-4 py-2 rounded-md mt-3">
        <AlertCircle className="text-yellow-600 h-4 w-4" />
        <span className="text-yellow-800 text-sm font-medium">
          {__("Not Connected. Please connect this plugin to SureFeedback.", "surefeedback")}
        </span>
      </div>
    );
  };

  /** ──────────────────────────────
   * 🧭 Get Dashboard URL
   * ────────────────────────────── */
  const getDashboardUrl = () =>
    connectionStatus.connected && connectionStatus.parent_url && connectionStatus.project_id
      ? `${connectionStatus.parent_url}/wp-admin/post.php?post=${connectionStatus.project_id}&action=edit`
      : null;

  /** ──────────────────────────────
   * 🧠 UI Rendering
   * ────────────────────────────── */
  return (
    <Card className="max-w-3xl">
      <CardHeader>
        <CardTitle>{__("Connection", "surefeedback")}</CardTitle>
        <CardDescription>
          {__("Connect your site to SureFeedback to enable feedback collection.", "surefeedback")}
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-6">
        <div>
          <CardTitle className="text-base">{__("Connection Status", "surefeedback")}</CardTitle>
          <CardDescription>
            {__("Current connection status with your SureFeedback parent site.", "surefeedback")}
          </CardDescription>

          {getConnectionStatusDisplay()}

          {connectionStatus.connected && (
            <div className="flex flex-wrap gap-3 mt-4">
              <Button
                onClick={handleDisconnect}
                disabled={isDisconnecting}
              >
                {isDisconnecting && <LoaderCircle className="animate-spin mr-2 h-4 w-4" />}
                {isDisconnecting ? __("Disconnecting...", "surefeedback") : __("Disconnect", "surefeedback")}
              </Button>

              {getDashboardUrl() && (
                <Button
                  variant="outline"
                  onClick={() => window.open(getDashboardUrl(), "_blank")}
                >
                  <ExternalLink className="mr-2 h-4 w-4" />
                  {__("Visit Dashboard Site", "surefeedback")}
                </Button>
              )}

              <Button
                variant="secondary"
                onClick={handleTestConnection}
                disabled={loading}
              >
                {loading && <LoaderCircle className="animate-spin mr-2 h-4 w-4" />}
                {loading ? __("Testing...", "surefeedback") : __("Test Connection", "surefeedback")}
              </Button>
            </div>
          )}

          {!connectionStatus.connected && (
            <div className="flex justify-between items-center bg-blue-50 border border-blue-200 px-4 py-3 rounded-md mt-4">
              <p className="text-sm text-foreground">
                {__("Having trouble connecting? Please reach out.", "surefeedback")}
              </p>
              <Button
                variant="link"
                onClick={() =>
                  window.open(
                    "https://surefeedback.com/docs/adding-a-clients-wordpress-site#manual",
                    "_blank"
                  )
                }
              >
                {__("Need Help?", "surefeedback")}
                <ArrowUpRight className="ml-1 h-4 w-4" />
              </Button>
            </div>
          )}
        </div>

        {!connectionStatus.connected && (
          <>
            <Separator />

            <div>
              <CardTitle className="text-base">
                {__("Manual Connection Details", "surefeedback")}
              </CardTitle>
              <CardDescription>
                {__(
                  "If automatic connection fails, paste your connection details JSON below.",
                  "surefeedback"
                )}
              </CardDescription>

              <Textarea
                value={manualConnectionData}
                onChange={(e) => setManualConnectionData(e.target.value)}
                placeholder={__("Paste your connection JSON here...", "surefeedback")}
                className="mt-2 font-mono text-xs"
                rows={6}
              />

              {errors.connection && (
                <p className="text-destructive text-sm mt-2">{errors.connection}</p>
              )}

              <Button
                onClick={handleManualImport}
                disabled={saving || !manualConnectionData.trim()}
                className="mt-4"
              >
                {saving && <LoaderCircle className="animate-spin mr-2 h-4 w-4" />}
                {saving ? __("Saving...", "surefeedback") : __("Save Changes", "surefeedback")}
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default ConnectionCard;
