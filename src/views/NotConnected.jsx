import React from "react";
import { XCircle, ChevronRight } from "lucide-react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "../helpers/auth";

const NotConnected = ({ setIsStarted }) => {
  return (
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 text-center max-w-md w-full">
        <XCircle className="mx-auto text-red-500 w-8 h-8 mb-1" />
        <h2 className="text-xl font-semibold text-gray-900 mb-1">
          {__("SureFeedback Not Connected!", "")}
        </h2>
        <div className="flex items-center justify-center">
          <p className="text-gray-500 mb-6" style={{ width: "260px" }}>
            {__(
              'Click "Connect Website" to authorize this website with SureFeedback.',
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
            onClick={() =>  authenticateRedirect()}
            className="px-8"
            style={{ borderRadius: "0.5rem" }}
          >
            {__("Connect Website", "")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default NotConnected;