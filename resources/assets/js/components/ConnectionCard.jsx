import React, { useState, useEffect, useCallback, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { Label } from "@/components/ui/label";
import { toast } from "@/components/ui/toast";
import {
  Loader2,
  CheckCircle,
  AlertCircle,
  ExternalLink,
  Link as LinkIcon,
  Unplug,
} from "lucide-react";
import { __ } from "@wordpress/i18n";

const ConnectionCard = () => {
  const [manualConnectionData, setManualConnectionData] = useState("");
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState({ connected: false });
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
          <Loader2 className="animate-spin h-4 w-4 text-muted-foreground" />
          <span className="text-foreground text-sm">
            {__("Loading connection status...", "surefeedback")}
          </span>
        </div>
      );
    }

    if (connectionStatus?.connected && connectionStatus?.parent_url) {
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
    connectionStatus?.connected && connectionStatus?.parent_url && connectionStatus?.project_id
      ? `${connectionStatus.parent_url}/wp-admin/post.php?post=${connectionStatus.project_id}&action=edit`
      : null;

  /** ──────────────────────────────
   * 🧠 UI Rendering
   * ────────────────────────────── */
  if (loading && !hasInitiallyLoaded) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        <span className="ml-3 text-sm text-muted-foreground">
          {__("Loading connection status...", "surefeedback")}
        </span>
      </div>
    );
  }

  // Connected State
  if (connectionStatus?.connected) {
    return (
      <div className="max-w-7xl mx-auto pt-8 px-6 pb-8 space-y-6">
        {/* Page Header */}
        <div className="space-y-1">
          <h1 className="text-3xl font-bold text-foreground">
            {__("Connection", "surefeedback")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {__("Your site is now linked with SureFeedback. Start gathering client feedback without friction.", "surefeedback")}
          </p>
        </div>

        <Separator />

        {/* Connection Status Card */}
        <Card className="border">
          <CardHeader className="pb-4">
            <div className="flex items-center gap-3">
              <CheckCircle className="h-5 w-5 text-green-600" />
              <div>
                <CardTitle className="text-lg font-semibold text-green-700">
                  {__("Website Connected Successfully", "surefeedback")}
                </CardTitle>
                <CardDescription className="mt-0.5">
                  {__("This site is actively connected to your SureFeedback parent dashboard.", "surefeedback")}
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent className="pt-0 space-y-4">
            <div className="flex items-center justify-between p-4 bg-muted rounded-lg border">
              <div className="flex items-start gap-3 flex-1">
                <LinkIcon className="h-5 w-5 text-muted-foreground mt-0.5 flex-shrink-0" />
                <div className="space-y-0.5">
                  <Label className="text-base font-semibold text-foreground">
                    {__("Connected Site", "surefeedback")}
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    {connectionStatus.parent_url}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2 bg-green-100 text-green-800 px-3 py-1.5 rounded-md flex-shrink-0">
                <CheckCircle className="h-4 w-4" />
                <span className="text-sm font-medium">{__("Active", "surefeedback")}</span>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex flex-col sm:flex-row gap-3 pt-8">
              {getDashboardUrl() && (
                <Button
                  size="lg"
                  onClick={() => window.open(getDashboardUrl(), "_blank")}
                  className="shadow-none"
                >
                  <ExternalLink className="mr-2 h-4 w-4" />
                  {__("Go to Dashboard", "surefeedback")}
                </Button>
              )}
              <Button
                variant="destructive"
                size="lg"
                onClick={handleDisconnect}
                disabled={isDisconnecting}
                className="shadow-none"
              >
                {isDisconnecting ? (
                  <>
                    <Loader2 className="animate-spin mr-2 h-4 w-4" />
                    {__("Disconnecting...", "surefeedback")}
                  </>
                ) : (
                  <>
                    <Unplug className="mr-2 h-4 w-4" />
                    {__("Disconnect", "surefeedback")}
                  </>
                )}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Disconnected State
  return null;
};

export default ConnectionCard;
