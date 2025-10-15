import React from "react";
import { Button } from "@bsf/force-ui";
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
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 max-w-lg w-full text-center">
        <div className="text-3xl mb-3">⚠️</div>
        <div className="flex flex-col items-center justify-center">
          <h2 className="text-xl font-semibold text-gray-800 mb-2">
            {getTitle()}
          </h2>
          <p className="text-gray-500 mb-6 text-sm w-80 text-center">
            {getDescription()}
          </p>
        </div>
        <div className="flex justify-center">
          <Button 
            variant="primary"
            onClick={handleAction}
          >
            {getButtonText()}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default UnverifiedState;
