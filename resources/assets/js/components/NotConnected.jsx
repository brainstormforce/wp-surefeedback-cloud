import React from "react";
import { AlertTriangle, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "@/helpers/auth";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-6 py-8 min-h-[400px]">
          {/* Icon */}
          <div className="w-20 h-20 mx-auto bg-orange-100 rounded-full flex items-center justify-center">
            <div className="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center">
              <AlertTriangle className="w-6 h-6 text-white" />
            </div>
          </div>
          
          {/* Content */}
          <div className="space-y-4">
            <h2 className="text-xl font-semibold text-orange-600">
              {__("SureFeedback Not Connected!", "surefeedback")}
            </h2>
            <p className="text-muted-foreground">
              {__(
                'Your WordPress site is not connected to SureFeedback. Click "Connect Website" to authorize this website and start collecting feedback.',
                "surefeedback"
              )}
            </p>
          </div>
          
          {/* Action Button */}
          <Button
            onClick={() => authenticateRedirect()}
            size="sm"
          >
            {__("Connect Website", "surefeedback")}
            <ChevronRight className="ml-2 h-4 w-4" />
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default NotConnected;
