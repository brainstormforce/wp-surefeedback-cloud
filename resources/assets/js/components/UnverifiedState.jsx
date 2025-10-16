import React from "react";
import { AlertTriangle } from "lucide-react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { __ } from "@wordpress/i18n";

const UnverifiedState = () => {
  const verificationStatus = window.sureFeedbackAdmin?.verification_status || 'unverified';
  
  const handleAction = () => {
    if (verificationStatus === 'pending') {
      // Go to dashboard
      const appUrl = window.sureFeedbackAdmin?.connection?.app_url || 'http://localhost:3000';
      window.open(`${appUrl}/sites`, '_blank');
    } else if (verificationStatus === 'failed') {
      // Reconnect
      window.location.reload();
    }
  };

  const getButtonText = () => {
    if (verificationStatus === 'pending') return __("Go to Dashboard", "surefeedback");
    if (verificationStatus === 'failed') return __("Reconnect", "surefeedback");
    return __("Go to Dashboard", "surefeedback");
  };

  const getTitle = () => {
    if (verificationStatus === 'pending') return __("Verification Pending", "surefeedback");
    if (verificationStatus === 'failed') return __("Verification Failed", "surefeedback");
    return __("Verification Required", "surefeedback");
  };

  const getDescription = () => {
    if (verificationStatus === 'pending') {
      return __("Your verification is in progress. You can check the status on your dashboard.", "surefeedback");
    }
    if (verificationStatus === 'failed') {
      return __("Verification failed. Please try reconnecting to complete the process.", "surefeedback");
    }
    return __("Your site needs to be verified before you can access the connection settings.", "surefeedback");
  };

  return (
    <div className="flex justify-center items-center min-h-screen bg-background p-4">
      <Card className="shadow-sm text-center max-w-md w-full">
        <CardContent className="space-y-4 p-6">
          <AlertTriangle className="mx-auto text-yellow-600 h-8 w-8" />
          <h2 className="text-xl font-semibold text-foreground">
            {getTitle()}
          </h2>
          <p className="text-sm text-muted-foreground">
            {getDescription()}
          </p>
          <Button
            size="default"
            onClick={handleAction}
          >
            {getButtonText()}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default UnverifiedState;
