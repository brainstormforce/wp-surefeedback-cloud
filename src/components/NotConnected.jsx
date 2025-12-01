import React from "react";
import { ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "@/helpers/auth";
import defaultConnectionImg from "../../assets/images/settings/default-connection.svg";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-start bg-background p-4 pt-8">
      <Card className="shadow-sm text-center max-w-2xl w-full rounded-lg border border-border">
        <CardContent className="flex flex-col justify-center items-center space-y-6 px-6 py-8 min-h-[400px]">
          {/* Icon */}
          <img src={defaultConnectionImg} alt="Not Connected" className="w-18 h-18" />
          
          {/* Content */}
          <div className="space-y-4 text-center">
            <h2 className="text-2xl font-semibold text-[#0F172A]">
              {__("SureFeedback Not Connected!", "surefeedback")}
            </h2>
            <p className="text-muted-foreground text-sm max-w-[300px] mx-auto">
              {__(
                'Click “Connect Website” to authorize this website with SureFeedback.',
                "surefeedback"
              )}
            </p>
          </div>
          
          {/* Action Button */}
          <Button
            onClick={() => authenticateRedirect()}
            className="bg-primary w-[300px] h-[48px] text-sm rounded-lg"
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
