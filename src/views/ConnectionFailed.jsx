import React from "react";
import { Frown, ChevronRight } from "lucide-react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { reconnectSite } from "../helpers/auth";

const ConnectionFailed = () => {
  const handleConnectAgain = () => {
    // Trigger reconnection flow
    reconnectSite();
  };

  return (
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 text-center max-w-md w-full">
        <Frown className="mx-auto text-red-500 w-8 h-8 mb-1" />
        <h2 className="text-xl font-semibold text-gray-900 mb-1">
          {__("Connection Failed...", "")}
        </h2>
        <div className="flex items-center justify-center">
          <p className="text-gray-500 mb-6">
            {__(
              'We couldn’t connect your site. Please try again.',
              ""
            )}
          </p>
        </div>
        <div className="flex items-center justify-center">
          <Button
            variant="primary"
            icon={<ChevronRight />}
            iconPosition="right"
            size="md"
            onClick={() => handleConnectAgain()}
            className="px-8"
            style={{ borderRadius: "0.5rem" }}
          >
            {__("Connect Again", "")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default ConnectionFailed;