import React from "react";
import { ChevronRight } from "lucide-react";
import { Button } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "../helpers/auth";

const Welcome = ({ setCurrentStep }) => {
  return (
    <div style={{ width: "850px", backgroundColor: "#FFFFFF" }}>
      {/* Top content */}
      <div className="flex flex-col items-center justify-center pt-12">
        <img
          src={`${sureFeedbackAdmin.surefeedback_icon}`}
          alt={__("Custom SVG", "surefeedback")}
          className="object-contain"
        />
        <h1 className="text-2xl font-semibold mt-4">
          Welcome to the Setup Wizard!
        </h1>
        <p
          className="text-center text-gray-600 mt-2"
          style={{ width: "460px" }}
        >
          SureFeedback makes it easy to collect and manage customer comments,
          helping you take action and improve satisfaction with less effort.
        </p>
        <Button
          icon={<ChevronRight />}
          type="submit"
          style={{
            backgroundColor: "#4353FF",
            marginTop: "14px",
            borderRadius: "6px",
          }}
          iconPosition="right"
          onClick={() => {
            authenticateRedirect();
          }}
        >
          {__("Get Started Now", "surefeedback")}
        </Button>
        <Button
          variant="link"
          type="submit"
          iconPosition="left"
          style={{ marginTop: "20px" }}
          onClick={() => {
            window.location.href = `${window.origin}/wp-admin`;
          }}
        >
          {__("Go Back to the dashboard", "surefeedback")}
        </Button>
      </div>
      {/* Middle Content */}
      <div className="mt-8 flex items-center justify-center">
        <div
          className="relative bg-cover bg-center bg-no-repeat"
          style={{
            backgroundImage: `url(${sureFeedbackAdmin.welcome_background})`,
            width: "730px",
            height: "437px",
          }}
        >
          <img
            src={`${sureFeedbackAdmin.welcome}`}
            alt={__("Welcome Image", "surefeedback")}
            className="absolute mx-auto inset-0"
            style={{
              width: "623px",
              height: "437px",
            }}
          />
        </div>
      </div>
      {/* Before Footer content */}
      <div className="mt-10 bg-[#E1F9FF] rounded-lg p-10 w-full max-w-3xl mx-auto">
        <h2 className="text-2xl font-semibold text-center text-gray-900">
          {__("Accelerate Issue Resolution with", "surefeedback")}
          <br />
          {__("Seamless Client Collaboration", "surefeedback")}
        </h2>

        {/* Features Grid */}
        <div className="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Card 1 */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <div className="flex items-center justify-start">
              <img
                src={`${sureFeedbackAdmin.thumbs}`}
                alt={__("Lightweight Icon", "surefeedback")}
                className="w-8 h-8 mr-4"
              />
              <h3 className="text-lg font-semibold">
                {__("Lightweight and Easy to Use", "surefeedback")}
              </h3>
            </div>
            <p className="text-gray-600 text-sm mt-1">
              {__(
                "SureFeedback is lightweight, bloat-free, and user-friendly with no training required.",
                "surefeedback"
              )}
            </p>
          </div>

          {/* Card 2 */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <div className="flex items-center justify-start">
              <img
                src={`${sureFeedbackAdmin.rocket}`}
                alt={__("Bug Report Icon", "surefeedback")}
                className="w-8 h-8 mr-4"
              />
              <h3 className="text-lg font-semibold">
                {__("Report Bugs Easily", "surefeedback")}
              </h3>
            </div>
            <p className="text-gray-600 text-sm mt-1">
              {__(
                "Bug reporting and tracking made easy. Simply point your cursor and leave review comments.",
                "surefeedback"
              )}
            </p>
          </div>

          {/* Card 3 */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <div className="flex items-center justify-start">
              <img
                src={`${sureFeedbackAdmin.admin}`}
                alt={__("Client Approval Icon", "surefeedback")}
                className="w-8 h-8 mr-4"
              />
              <h3 className="text-lg font-semibold">
                {__("1-click Client Approvals", "surefeedback")}
              </h3>
            </div>
            <p className="text-gray-600 text-sm mt-1">
              {__(
                "Clients can review and approve designs/projects with one click, reducing email exchanges.",
                "surefeedback"
              )}
            </p>
          </div>

          {/* Card 4 */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <div className="flex items-center justify-start">
              <img
                src={`${sureFeedbackAdmin.docs}`}
                alt={__("Records Icon", "surefeedback")}
                className="w-8 h-8 mr-4"
              />
              <h3 className="text-lg font-semibold">
                {__("Keeps Records", "surefeedback")}
              </h3>
            </div>
            <p className="text-gray-600 text-sm mt-1">
              {__(
                "SureFeedback records all feedback, simplifying referencing and tracking revisions.",
                "surefeedback"
              )}
            </p>
          </div>
        </div>
      </div>
      {/* Footer Content */}
      <div className="mt-12 flex flex-col items-center justify-center text-center pb-12">
        <h1 className="w-72 text-xl font-semibold">
          {__(
            "One Dashboard. Unlimited Websites. Any Platform.",
            "surefeedback"
          )}
        </h1>
        <p className="mt-2 w-120 text-sm font-normal text-text-tertiary ">
          {__(
            "Once installed on WordPress, SureFeedback works on unlimited websites, ANY platform. Central dashboard management included.",
            "surefeedback"
          )}
        </p>
        <img
          src={`${sureFeedbackAdmin.footer}`}
          alt={__("Custom SVG", "surefeedback")}
          className="object-contain mt-8"
          style={{ width: "730px", height: "130px" }}
        />
        <Button
          icon={<ChevronRight />}
          type="submit"
          style={{
            backgroundColor: "#4353FF",
            marginTop: "48px",
            borderRadius: "6px",
          }}
          iconPosition="right"
          onClick={() => {
            authenticateRedirect();
          }}
        >
          {__("Get Started Now", "surefeedback")}
        </Button>
        <Button
          variant="link"
          type="submit"
          iconPosition="left"
          style={{ marginTop: "20px" }}
          onClick={() => {
            window.location.href = `${window.origin}/wp-admin`;
          }}
        >
          {__("Go Back to the dashboard", "surefeedback")}
        </Button>
      </div>
    </div>
  );
};

export default Welcome;
