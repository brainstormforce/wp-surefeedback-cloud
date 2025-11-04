import React, { useState } from "react";
import { AlertTriangle, CheckCircle, Loader2, RefreshCw, ExternalLink } from "lucide-react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { __ } from "@wordpress/i18n";
import { useVerification } from "../hooks";

const UnverifiedState = ({ showLoading = false, onRetryVerification = null, verificationResult = null }) => {
  const { verifyConnection, isLoading: verificationLoading } = useVerification();
  const dbVerificationStatus = window.sureFeedbackAdmin?.verification_status || 'unverified';
  const verificationStatus = verificationResult?.status || dbVerificationStatus;
  const [isTestingConnection, setIsTestingConnection] = useState(false);
  const isLoading = isTestingConnection || verificationLoading || (showLoading && !verificationResult);

  // Automatically call verification on mount if onRetryVerification is provided and not already loading
  React.useEffect(() => {
    if (onRetryVerification && !showLoading && !verificationResult) {
      onRetryVerification();
    }
  }, []);
  
  const handleAction = () => {
    const appUrl = window.sureFeedbackAdmin?.connection?.app_url || 'https://app.surefeedback.com';
    window.open(`${appUrl}/sites`, '_blank');
  };

  const handleTestConnection = async () => {
    setIsTestingConnection(true);
    try {
      const result = await verifyConnection({});
      if (result.status === 'verified') {
        window.location.reload();
      } else if (result.status === 'pending') {
        window.location.reload();
      } else {
        window.location.reload();
      }
    } catch (error) {
      // Error handling without alert
    } finally {
      setIsTestingConnection(false);
    }
  };

  const getIconAndColor = () => {
    return {
      icon: AlertTriangle,
      bgColor: 'bg-yellow-100',
      iconBgColor: 'bg-yellow-500',
      textColor: 'text-yellow-600'
    };
  };

  const { icon: Icon, bgColor, iconBgColor, textColor } = getIconAndColor();

  const getTitle = () => {
    return __("Verification Pending", "surefeedback");
  };

  const getDescription = () => {
    if (verificationResult && verificationResult.message) {
      return verificationResult.message + '. ' + __("Please add the SureFeedback script to complete integration.", "surefeedback");
    }
    return __("Your verification is in progress. You can check the status on your dashboard.", "surefeedback");
  };

  const getButtonText = () => {
    return __("Go to Dashboard", "surefeedback");
  };

  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-6 py-8 min-h-[400px]">
          {isLoading ? (
            // Testing Connection State
            <>
              <div className="w-20 h-20 mx-auto bg-yellow-100 rounded-full flex items-center justify-center">
                <div className="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center">
                  <Loader2 className="w-6 h-6 text-white animate-spin" />
                </div>
              </div>
              <div className="space-y-4">
                <h2 className="text-xl font-semibold text-yellow-600">
                  {__("Checking Connection", "surefeedback")}
                </h2>
                <p className="text-muted-foreground">
                  {__("Verifying your WordPress site connection to SureFeedback. Please wait while we check the integration status.", "surefeedback")}
                </p>
              </div>
            </>
          ) : (
            <>
              <div className={`w-20 h-20 mx-auto ${bgColor} rounded-full flex items-center justify-center`}>
                <div className={`w-12 h-12 ${iconBgColor} rounded-full flex items-center justify-center`}>
                  <Icon className="w-6 h-6 text-white" />
                </div>
              </div>
              <div className="space-y-4">
                <h2 className={`text-xl font-semibold ${textColor}`}>
                  {getTitle()}
                </h2>
                <p className="text-muted-foreground">
                  {getDescription()}
                </p>
              </div>
              <div className="flex gap-3">
                <Button
                  size="default"
                  onClick={handleTestConnection}
                  disabled={isLoading}
                  className="flex items-center"
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  {__("Test Connection", "surefeedback")}
                </Button>
                <Button
                  variant="outline"
                  size="default"
                  onClick={handleAction}
                  className="flex items-center"
                >
                  <ExternalLink className="w-4 h-4 mr-2" />
                  {getButtonText()}
                </Button>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default UnverifiedState;
