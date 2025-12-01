import React from "react";
import { Frown, ChevronRight } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { __ } from "@wordpress/i18n";
import { reconnectSite } from "../helpers/auth";

const ConnectionFailed = ({ verificationResult }) => {
  const handleConnectAgain = () => {
    // Trigger reconnection flow
    reconnectSite();
  };

  const errorMessage = verificationResult?.message || __("We couldn't connect your site. Please try again.", "surefeedback");

  return (
    <div className="flex justify-center items-center min-h-screen bg-background">
      <Card className="shadow-sm text-center max-w-md w-full">
        <CardContent className="space-y-4 p-4">
          <Frown className="mx-auto text-destructive h-8 w-8" />
          <h2 className="text-xl font-semibold text-foreground">
            {__("Connection Failed...", "surefeedback")}
          </h2>
          <p className="text-muted-foreground">
            {errorMessage}
          </p>
          <Button
            size="default"
            onClick={() => handleConnectAgain()}
          >
            {__("Connect Again", "surefeedback")}
            <ChevronRight className="ml-2 h-4 w-4" />
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default ConnectionFailed;