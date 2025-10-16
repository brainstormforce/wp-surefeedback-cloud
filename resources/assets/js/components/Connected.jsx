import React, { useState } from "react";
import { Button } from "../components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card";
import { Separator } from "../components/ui/separator";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "../components/ui/dialog";
import { __ } from "@wordpress/i18n";
import { CheckCircle, AlertTriangle, Loader2, ExternalLink } from "lucide-react";
import { disconnectSite } from "../helpers/auth";

const Connected = () => {
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [disconnectStatus, setDisconnectStatus] = useState(null); // null, 'success', 'error'
  const [errorMessage, setErrorMessage] = useState('');
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleDisconnectClick = () => {
    if (isDisconnecting) return;
    setIsDialogOpen(true);
  };

  const confirmDisconnect = async () => {
    setIsDialogOpen(false);
    setIsDisconnecting(true);
    setDisconnectStatus(null);
    setErrorMessage('');

    try {
      const result = await disconnectSite();
      
      if (result.success) {
        setDisconnectStatus('success');
        
        // Reload the page after short delay
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        setDisconnectStatus('error');
        setErrorMessage(result.error || __('Failed to disconnect site. Please try again.', 'surefeedback'));
      }
    } catch (error) {
      setDisconnectStatus('error');
      setErrorMessage(__('Network error occurred. Please try again.', 'surefeedback'));
    } finally {
      setIsDisconnecting(false);
    }
  };

  const handleGoToDashboard = () => {
    const appUrl = window.sureFeedbackAdmin?.connection?.app_url || 'http://localhost:3000';
    window.open(`${appUrl}/sites`, '_blank');
  };

  return (
    <div className="w-full flex flex-col items-center bg-background py-10 pt-4">
      <Card className="w-full max-w-3xl text-center border-none shadow-none">
        <CardHeader>
          <div className="text-6xl mb-4">🎉</div>
          <CardTitle className="text-2xl font-semibold text-foreground">
            {__("Website Connected Successfully!", "surefeedback")}
          </CardTitle>
          <CardDescription className="text-muted-foreground mt-2">
            {__(
              "Your site is now linked with SureFeedback. Start gathering client feedback without friction.",
              "surefeedback"
            )}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col items-center gap-6 mt-4">
          {/* Disconnect Status Feedback */}
          {disconnectStatus && (
            <Card className={disconnectStatus === 'success'
              ? 'bg-green-50 border-green-200 w-full max-w-md'
              : 'bg-red-50 border-red-200 w-full max-w-md'
            }>
              <CardContent className="pt-6">
                {disconnectStatus === 'success' ? (
                  <div className="flex items-center justify-center gap-2 text-green-700">
                    <CheckCircle className="h-5 w-5" />
                    <span className="text-sm font-medium">
                      {__("Site disconnected successfully! Redirecting...", "surefeedback")}
                    </span>
                  </div>
                ) : (
                  <div className="flex items-center justify-center gap-2 text-red-700">
                    <AlertTriangle className="h-5 w-5" />
                    <span className="text-sm font-medium">{errorMessage}</span>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* Connection Details Card */}
          <Card className="w-full max-w-md bg-muted border-border">
            <CardContent className="pt-6 space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-foreground">
                  {__("Connection Site:", "surefeedback")}
                </span>
                <span className="text-sm text-muted-foreground">
                  {window.sureFeedbackAdmin?.connection?.site_data?.site_url || 'Unknown'}
                </span>
              </div>
              <Separator />
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-foreground">
                  {__("Status:", "surefeedback")}
                </span>
                <div className="flex items-center gap-2 bg-green-100 text-green-700 px-3 py-1 rounded-full">
                  <CheckCircle className="h-4 w-4" />
                  <span className="text-sm font-medium">{__("Active", "surefeedback")}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-3 mt-2">
            <Button
              size="default"
              onClick={handleGoToDashboard}
            >
              {__("Go to Dashboard", "surefeedback")}
              <ExternalLink className="ml-2 h-4 w-4" />
            </Button>
            <Button
              variant="destructive"
              onClick={handleDisconnectClick}
              disabled={isDisconnecting}
            >
              {isDisconnecting ? (
                <>
                  <Loader2 className="animate-spin mr-2 h-4 w-4" />
                  {__("Disconnecting...", "surefeedback")}
                </>
              ) : (
                __("Disconnect", "surefeedback")
              )}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {__("Disconnect Site", "surefeedback")}
            </DialogTitle>
            <DialogDescription>
              {__("Are you sure you want to disconnect this site from SureFeedback? This will deactivate the widget and clear all connection data.", "surefeedback")}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
              {__("Cancel", "surefeedback")}
            </Button>
            <Button variant="destructive" onClick={confirmDisconnect}>
              {__("Yes, Disconnect", "surefeedback")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default Connected;