import React from "react";
import { CheckCircle, ExternalLink, Unplug } from "lucide-react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { __ } from "@wordpress/i18n";

const ConnectedState = ({ connectionData = {} }) => {
  const connection = connectionData || window.sureFeedbackAdmin?.connection || {};
  const parentUrl = connection.parent_url || connection.app_url || 'http://localhost:3000';
  const siteId = connection.site_id || window.sureFeedbackAdmin?.connection?.site_id;
  
  const handleGoToDashboard = () => {
    const dashboardUrl = `${parentUrl}/sites`;
    window.open(dashboardUrl, '_blank');
  };

  const handleDisconnect = () => {
    if (confirm(__('Are you sure you want to disconnect? This will disable the feedback widget.', 'surefeedback'))) {
      // Handle disconnect logic
      window.location.href = window.sureFeedbackAdmin.adminUrl + '&action=disconnect';
    }
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

          {/* Title and Description */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-green-600">
              {__("Website Connected Successfully!", "surefeedback")}
            </h2>
            <p className="text-muted-foreground">
              {__("Your site is now linked with SureFeedback. Start gathering client feedback without friction.", "surefeedback")}
            </p>
          </div>

          {/* Connection Details */}
          <div className="w-full max-w-md space-y-4 bg-gray-50 p-4 rounded-lg">
            <div className="flex justify-between items-center">
              <span className="font-medium text-gray-700">
                {__("Connection Site:", "surefeedback")}
              </span>
              <span className="text-gray-600 text-sm">{parentUrl}</span>
            </div>
            
            <div className="flex justify-between items-center">
              <span className="font-medium text-gray-700">
                {__("Status:", "surefeedback")}
              </span>
              <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <CheckCircle className="w-3 h-3 mr-1" />
                {__("Active", "surefeedback")}
              </span>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex gap-3 w-full max-w-md">
            <Button
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white"
              onClick={handleGoToDashboard}
            >
              <ExternalLink className="w-4 h-4 mr-2" />
              {__("Go to Dashboard", "surefeedback")}
            </Button>
            
            <Button
              variant="outline"
              className="flex-1 border-red-300 text-red-600 hover:bg-red-50"
              onClick={handleDisconnect}
            >
              <Unplug className="w-4 h-4 mr-2" />
              {__("Disconnect", "surefeedback")}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default ConnectedState;