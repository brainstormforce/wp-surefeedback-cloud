import React from "react";
import { Frown, ChevronRight } from "lucide-react";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { __ } from "@wordpress/i18n";
import { reconnectSite } from "../helpers/auth";

const ConnectionFailed = () => {
  const handleConnectAgain = () => {
    // Trigger reconnection flow
    reconnectSite();
  };

  return (
    <div className="flex justify-center items-center min-h-screen bg-background">
      <Card className="shadow-sm text-center max-w-md w-full">
        <CardContent className="space-y-4 p-4">
          <Frown className="mx-auto text-destructive h-8 w-8" />
          <h2 className="text-xl font-semibold text-foreground">
            {__("Connection Failed...", "")}
          </h2>
          <p className="text-muted-foreground">
            {__(
              "We couldn't connect your site. Please try again.",
              ""
            )}
          </p>
          <Button
            size="default"
            onClick={() => handleConnectAgain()}
          >
            {__("Connect Again", "")}
            <ChevronRight className="ml-2 h-4 w-4" />
          </Button>
        </CardContent>
      </Card>
    </div>
  );
};

export default ConnectionFailed;