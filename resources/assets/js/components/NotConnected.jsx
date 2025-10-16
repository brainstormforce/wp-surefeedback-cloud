import React from "react";
import { XCircle, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardContent, CardTitle, CardDescription } from "@/components/ui/card";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "@/helpers/auth";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-center min-h-screen bg-background p-4">
      <Card className="shadow-sm text-center max-w-xl w-full">
        <CardHeader>
          <XCircle className="mx-auto text-destructive h-10 w-10" />
          <CardTitle className="text-xl font-semibold text-foreground mt-3">
            {__("SureFeedback Not Connected!", "surefeedback")}
          </CardTitle>
          <CardDescription className="text-muted-foreground mt-2">
            {__(
              'Click "Connect Website" to authorize this website with SureFeedback.',
              "surefeedback"
            )}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex justify-center pb-6">
          <Button
            onClick={() => authenticateRedirect()}
            size="default"
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
