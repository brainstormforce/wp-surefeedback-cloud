import React, { useState } from "react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { Separator } from "../components/ui/separator";
import { __ } from "@wordpress/i18n";
import {
  CheckCircle,
  AlertTriangle,
  Loader2,
  ExternalLink,
  Unplug,
} from "lucide-react";

const Connected = ({ connectionData, verificationResult }) => {
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [disconnectStatus, setDisconnectStatus] = useState(null); // null, 'success', 'error'
  const [errorMessage, setErrorMessage] = useState("");

  const handleDisconnectClick = () => {
    if (isDisconnecting) return;
    setAcceptedTerms(false); // Reset terms when opening dialog
    setIsDialogOpen(true);
  };

  const confirmDisconnect = async () => {
   // To Be Implemented
  };

  const handleGoToDashboard = () => {
    const appUrl =
      window.sureFeedbackAdmin?.connection?.app_url ||
      "http://localhost:3000";
    window.open(`${appUrl}/sites`, "_blank");
  };

  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-6 py-8 min-h-[400px]">
          {/* Success Icon */}
          <div className="w-20 h-20 mx-auto bg-green-100 rounded-full flex items-center justify-center">
            <div className="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
              <CheckCircle className="w-6 h-6 text-white" />
            </div>
          </div>

          {/* Message */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-green-600">
              {__("Website Connected Successfully!", "surefeedback")}
            </h2>
            <p className="text-muted-foreground">
              {__(
                "Your site is now linked with SureFeedback. Start gathering client feedback without friction.",
                "surefeedback"
              )}
            </p>
          </div>

          {/* Status Feedback */}
          {disconnectStatus && (
            <div
              className={`p-4 rounded-lg w-full ${
                disconnectStatus === "success"
                  ? "bg-green-50 border border-green-200"
                  : "bg-red-50 border border-red-200"
              }`}
            >
              {disconnectStatus === "success" ? (
                <div className="flex items-center justify-center gap-2 text-green-700">
                  <CheckCircle className="h-5 w-5" />
                  <span className="text-sm font-medium">
                    {__(
                      "Site disconnected successfully! Redirecting...",
                      "surefeedback"
                    )}
                  </span>
                </div>
              ) : (
                <div className="flex items-center justify-center gap-2 text-red-700">
                  <AlertTriangle className="h-5 w-5" />
                  <span className="text-sm font-medium">{errorMessage}</span>
                </div>
              )}
            </div>
          )}

          {/* Connection Info */}
          <div className="w-full bg-muted border border-border rounded-lg p-4 space-y-4">
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-foreground">
                {__("Connection Site:", "surefeedback")}
              </span>
              <span className="text-sm text-muted-foreground">
                {window.sureFeedbackAdmin?.connection?.site_data?.site_url ||
                  "Unknown"}
              </span>
            </div>
            <Separator />
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-foreground">
                {__("Status:", "surefeedback")}
              </span>
              <div className="flex items-center gap-2 bg-green-100 text-green-800 px-3 py-1 rounded-full">
                <CheckCircle className="h-4 w-4" />
                <span className="text-sm font-medium">
                  {__("Active", "surefeedback")}
                </span>
              </div>
            </div>
          </div>

          {/* Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 w-full">
            <Button size="sm" onClick={handleGoToDashboard} className="flex-1">
              <ExternalLink className="mr-2 h-4 w-4" />
              {__("Go to Dashboard", "surefeedback")}
            </Button>

            <Button
              variant="destructive"
              size="sm"
              onClick={handleDisconnectClick}
              disabled={isDisconnecting}
              className="flex-1"
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
};

export default Connected;
