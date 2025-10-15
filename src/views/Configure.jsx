import React from "react";
import { __ } from "@wordpress/i18n";
import { ChevronRight } from "lucide-react";
import { Button } from "@bsf/force-ui";

const Configure = () => {
  return (
    <div
      className="flex flex-col items-center justify-center"
      style={{ width: "850px", backgroundColor: "#FFFFFF", padding: "24px", borderRadius: "10px" }}
    >
      {/* Top Content */}
      <div className="flex flex-col items-center justify-center">
        <h1 className="text-2xl font-semibold">Almost There</h1>
        <p className="text-text-tertiary font-normal text-sm mt-2">
          Please link your SureFeedback account to proceed.
        </p>
        <img
          src={`${sureFeedbackAdmin.configure}`}
          alt={__("Custom SVG", "surefeedback")}
          className="object-contain w-56 h-10 mt-8"
        />
      </div>
      {/* Center Content */}
      <div className="mt-8 flex flex-col items-center justify-center">
        <p className="font-normal text-sm text-center w-72">
          Connect to your ishwarib@bsf.io account on SureFeedback in below
          Workspace.
        </p>
        <div className="">
          <input type="hidden" name="product-name" id="bsf-product-name" />
          <select
            id=""
            // ref={liteVersionRef}
            style={{
              padding: "8px",
              marginRight: "10px",
              marginTop: "16px",
              cursor: "pointer",
              borderRadius: "10px",
              height: "52px",
              width: "300px",
              outline: "none", // Removes the default outline
              boxShadow: "none",
              // marginTop: '16px'     // Removes the default box shadow
            }}
            onFocus={(e) => (e.target.style.borderColor = "#6005FF")} // Apply focus color
          ></select>
        </div>
        <Button
          icon={<ChevronRight />}
          type="submit"
          style={{
            backgroundColor: "#4353FF",
            marginTop: "16px",
            borderRadius: "6px",
            width: "300px",
          }}
          iconPosition="right"
          // onClick={() => setCurrentStep(2)}
        >
          {__("Connect Site", "surefeedback")}
        </Button>
      </div>
      {/* Bottom Content */}
      <div>
        <p className="text-sm font-normal text-center mt-4">
          Not You? Use a different account
        </p>
        <p className="text-sm font-medium text-center mt-8">
          By clicking, you agree to our Terms and Privacy Policy
        </p>
      </div>
    </div>
  );
};

export default Configure;
