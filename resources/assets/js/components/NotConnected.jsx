import React from "react";
import { XCircle, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardContent, CardTitle, CardDescription } from "@/components/ui/card";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "@/helpers/auth";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-start bg-muted/10" style={{minHeight: 'calc(100vh - 46px)', padding: '18px'}}>
      <Card className="w-full max-w-[38rem] text-center shadow border border-gray-200 flex flex-col justify-center items-center" style={{minHeight: '350px'}}>
        <CardHeader>
          <XCircle className="mx-auto text-destructive w-10 h-10 mb-2" />
          <CardTitle className="text-xl font-semibold text-gray-900">
            {__("SureFeedback Not Connected!", "surefeedback")}
          </CardTitle>
          <CardDescription className="text-gray-500 mt-1">
            {__(
              'Click "Connect Website" to authorize this website with SureFeedback.',
              "surefeedback"
            )}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex justify-center mt-2">
          <Button
            onClick={() => authenticateRedirect()}
            className="px-6"
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
