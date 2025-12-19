import React, { useState } from "react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Separator } from "./ui/separator";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "./ui/alert-dialog";
import { toast } from "./ui/toast";
import { __ } from "@wordpress/i18n";
import {
  CheckCircle,
  AlertTriangle,
  Loader2,
  ExternalLink,
  Unplug,
} from "lucide-react";
import { apiGateway } from '../api/gateway.js';
import ConnectedConnection from "../../assets/images/settings/connection-connected.svg";
import PowerOff from "../../assets/images/settings/power_off.svg";

const Connected = ({ connectionData, verificationResult }) => {
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleDisconnectClick = () => {
    if (isDisconnecting) return;
    setIsDialogOpen(true);
  };

  const confirmDisconnect = async () => {
    setIsDisconnecting(true);

    try {
      // Use new REST API endpoint
      const data = await apiGateway.post('connection/disconnect');

      if (data.success || data.connected === false) {
        toast.success(__("Site disconnected successfully! Refreshing...", "surefeedback-cloud"));
        setIsDialogOpen(false);

        // Reload the current page to refresh the connection state
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else {
        toast.error(
          data.message ||
            __("Failed to disconnect. Please try again.", "surefeedback-cloud")
        );
        setIsDialogOpen(false);
      }
    } catch (error) {
      toast.error(
        __(
          "An error occurred while disconnecting. Please try again.",
          "surefeedback-cloud"
        )
      );
      setIsDialogOpen(false);
    } finally {
      setIsDisconnecting(false);
    }
  };

  const handleGoToDashboard = () => {
    window.open("https://app.surefeedback.com/sites", "_blank");
  };

  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full rounded-lg border border-border">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-16 py-8 min-h-[400px]">
          {/* Success Icon */}
            <img src={ConnectedConnection} alt="Connecting..." className="w-18 h-18" />
          {/* Message */}
          <div className="space-y-4 text-center">
            <h2 className="text-2xl font-semibold text-[#0F172A] ">
              {__("Website Connected Successfully!", "surefeedback-cloud")}
            </h2>
            <p className="text-muted-foreground text-sm max-w-[300px] mx-auto">
              {__(
                "Your site is now linked with SureFeedback. Start gathering client feedback without friction.",
                "surefeedback-cloud"
              )}
            </p>
          </div>

          {/* Connection Info */}
          <div className="w-full bg-muted border border-border rounded-lg p-4 space-y-4">
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-foreground">
                {__("Connection Site:", "surefeedback-cloud")}
              </span>
              <span className="text-sm text-muted-foreground">
                {window.surefeedbackAdmin?.siteUrl ||
                  window.sureFeedbackAdmin?.connection?.site_data?.site_url ||
                  window.location.origin ||
                  "Unknown"}
              </span>
            </div>
            <Separator />
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-foreground">
                {__("Status:", "surefeedback-cloud")}
              </span>
              <div className="flex items-center gap-2 bg-green-100 text-green-800 px-3 py-1 rounded-full">
                <CheckCircle className="h-4 w-4" />
                <span className="text-sm font-medium">
                  {__("Active", "surefeedback-cloud")}
                </span>
              </div>
            </div>
          </div>

          {/* Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 w-full">
            <Button
              size="sm"
              variant="destructive"
              onClick={handleDisconnectClick}
              disabled={isDisconnecting}
              className="flex-1 h-[48px] text-sm rounded-lg bg-[#FF5C5C]"
            >
              {isDisconnecting ? (
                <>
                  <Loader2 className="animate-spin h-4 w-4" />
                  {__("Disconnecting...", "surefeedback-cloud")}
                </>
              ) : (
                <>
                  {/* <Unplug className="mr-2 h-4 w-4" /> */}
                  <img src={PowerOff} alt="Disconnect" className="h-4 w-4" />
                  {__("Disconnect", "surefeedback-cloud")}
                </>
              )}
            </Button>

            <Button variant="outline" size="sm" onClick={handleGoToDashboard} className="flex-1 h-[48px] text-sm rounded-lg border border-[#020617]">
              <ExternalLink className=" h-4 w-4" />
              {__("Go to Dashboard", "surefeedback-cloud")}
            </Button>

          </div>
        </CardContent>
      </Card>

      {/* Disconnect Confirmation Dialog */}
      <AlertDialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {__("Disconnect Website?", "surefeedback-cloud")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {__(
                "This will disconnect your website from SureFeedback. All connection settings will be removed. You can reconnect at any time.",
                "surefeedback-cloud"
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDisconnecting}>
              {__("Cancel", "surefeedback-cloud")}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDisconnect}
              disabled={isDisconnecting}
              className="bg-red-600 hover:bg-red-700"
            >
              {isDisconnecting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  {__("Disconnecting...", "surefeedback-cloud")}
                </>
              ) : (
                __("Disconnect", "surefeedback-cloud")
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
};

export default Connected;
