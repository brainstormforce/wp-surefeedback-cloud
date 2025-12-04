import React, { useState } from "react";
import { AlertTriangle, CheckCircle, Loader2, RefreshCw, ExternalLink } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { toast } from "./ui/toast";
import { __ } from "@wordpress/i18n";
import { useVerification } from "../hooks";
import LoadingConnection from "../../assets/images/settings/connection-loading.svg";
import VerifyConnection from "../../assets/images/settings/connections-verification.svg";

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
        toast.success(__('Connection verified successfully!', 'surefeedback-cloud'));
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else if (result.status === 'pending') {
        toast.info(__('Verification is still pending. Please check your connection settings.', 'surefeedback-cloud'));
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        toast.warning(__('Connection verification failed. Please try again.', 'surefeedback-cloud'));
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      }
    } catch (error) {
      toast.error(__('An error occurred while testing the connection. Please try again.', 'surefeedback-cloud'));
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
    return __("Verification Pending", "surefeedback-cloud");
  };

  const getDescription = () => {
    if (verificationResult && verificationResult.message) {
      return verificationResult.message + '. ' + __("Please add the SureFeedback script to complete integration.", "surefeedback-cloud");
    }
    return __("Your verification is in progress. You can check the status on your dashboard.", "surefeedback-cloud");
  };

  const getButtonText = () => {
    return __("Go to Dashboard", "surefeedback-cloud");
  };

  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full rounded-lg border border-border">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-6 py-8 min-h-[400px]">
          {isLoading ? (
            <>
              <img src={LoadingConnection} alt="Connecting..." className="w-18 h-18 animate-spin" />
              <div className="space-y-4">
                <h2 className="text-2xl font-semibold text-[#0F172A]">
                  {__("Connecting...", "surefeedback-cloud")}
                </h2>
                <p className="text-muted-foreground text-sm max-w-[300px]">
                  {__("Please wait while we verify your website with SureFeedback servers.", "surefeedback-cloud")}
                </p>
              </div>
            </>
          ) : (
            <>
              <img src={VerifyConnection} alt="Vertifying Connection..." className="w-18 h-18" />
              <div className="space-y-4">
                <h2 className="text-2xl font-semibold text-[#0F172A]">
                  {getTitle()}
                </h2>
                <p className="text-muted-foreground text-sm max-w-[300px]">
                  {getDescription()}
                </p>
              </div>
              <div className="flex gap-3">
                <Button
                  size="default"
                  onClick={handleTestConnection}
                  disabled={isLoading}
                  className="flex items-center bg-primary h-[48px] text-sm rounded-lg"
                >
                  <RefreshCw className="w-4 h-4 mr-2" />
                  {__("Test Connection", "surefeedback-cloud")}
                </Button>
                <Button
                  variant="outline"
                  size="default"
                  onClick={handleAction}
                  className="flex items-center h-[48px] text-sm rounded-lg border border-[#020617]"
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
