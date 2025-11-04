import React from "react";
import { ChevronRight } from "lucide-react";
import { __ } from "@wordpress/i18n";
import { authenticateRedirect } from "@/helpers/auth";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardContent, CardTitle, CardDescription } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";

export const Welcome = ({ setCurrentStep }) => {
  const handleGetStarted = () => {
    const { sureFeedbackAdmin } = window;
    
    if (!sureFeedbackAdmin || !sureFeedbackAdmin.connection) {
      return;
    }

    const { connection } = sureFeedbackAdmin;

    // Generate a state token for security
    const state = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);

    const params = new URLSearchParams({
      source: 'wordpress',
      action: 'connect_site',
      callback_url: connection.callback_url,
      state: state,
      site_data: btoa(JSON.stringify(connection.site_data)),
    });

    // Construct the connection URL using localized app URL
    const connectUrl = `${connection.app_url}/connect?${params.toString()}`;
    
    // Store connection intent in sessionStorage for redirect after login
    const connectionIntent = {
      url: connectUrl,
      timestamp: Date.now(),
      source: 'wordpress_plugin'
    };
    
    // Store in both sessionStorage and localStorage for reliability
    sessionStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
    localStorage.setItem('surefeedback_connection_intent', JSON.stringify(connectionIntent));
    
    // 1. Open connect URL in new tab (target="_blank")
    window.open(connectUrl, '_blank');
    
    // 2. Change current screen to connections page
    const adminBaseUrl = window.sureFeedbackAdmin?.admin_url || `${window.location.origin}/wp-admin`;
    window.location.href = `${adminBaseUrl}/admin.php?page=surefeedback-connection#connections`;
  };
  return (
    <div className="w-full flex flex-col items-center bg-background py-10">
      {/* Top Section */}
      <Card className="w-full max-w-5xl text-center border-none shadow-none">
        <CardHeader>
          <img
            src={window.sureFeedbackAdmin?.surefeedback_icon || ""}
            alt={__("SureFeedback Logo", "surefeedback")}
            className="mx-auto h-18 w-18 object-contain pb-4"
          />
          <CardTitle className="text-2xl font-semibold text-foreground mt-3">
            {__("Welcome to the Setup Wizard!", "surefeedback")}
          </CardTitle>
          <CardDescription className="text-muted-foreground mt-2 max-w-md mx-auto">
            {__(
              "SureFeedback makes it easy to collect and manage customer comments, helping you take action and improve satisfaction with less effort.",
              "surefeedback"
            )}
          </CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col items-center gap-3 mt-2">
          <Button
            size="default"
            onClick={handleGetStarted}
          >
            {__("Get Started Now", "surefeedback")}
            <ChevronRight className="ml-2 h-4 w-4" />
          </Button>

          <Button
            variant="link"
            onClick={() => (window.location.href = `${window.origin}/wp-admin`)}
          >
            {__("Go Back to the Dashboard", "surefeedback")}
          </Button>
        </CardContent>
      </Card>

      {/* Middle Illustration */}
      <div className="mt-10 relative w-full max-w-5xl flex justify-center">
        <div
          className="relative bg-cover bg-center rounded-lg overflow-hidden w-full max-w-3xl"
          style={{
            backgroundImage: `url(${window.sureFeedbackAdmin?.welcome_background || ""})`,
            height: "440px",
          }}
        >
          <img
            src={window.sureFeedbackAdmin?.welcome || ""}
            alt={__("Welcome Illustration", "surefeedback")}
            className="absolute inset-0 mx-auto object-contain w-[90%] h-full"
          />
        </div>
      </div>

      {/* Features Section */}
      <Card className="mt-12 w-full max-w-5xl bg-muted border-none">
        <CardHeader>
          <CardTitle className="text-2xl text-center text-foreground font-semibold">
            {__("Accelerate Issue Resolution with", "surefeedback")}
            <br />
            {__("Seamless Client Collaboration", "surefeedback")}
          </CardTitle>
        </CardHeader>

        <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
          {[
            {
              icon: window.sureFeedbackAdmin?.thumbs,
              title: __("Lightweight and Easy to Use", "surefeedback"),
              desc: __(
                "SureFeedback is lightweight, bloat-free, and user-friendly with no training required.",
                "surefeedback"
              ),
            },
            {
              icon: window.sureFeedbackAdmin?.rocket,
              title: __("Report Bugs Easily", "surefeedback"),
              desc: __(
                "Bug reporting and tracking made easy. Simply point your cursor and leave review comments.",
                "surefeedback"
              ),
            },
            {
              icon: window.sureFeedbackAdmin?.admin,
              title: __("1-click Client Approvals", "surefeedback"),
              desc: __(
                "Clients can review and approve designs/projects with one click, reducing email exchanges.",
                "surefeedback"
              ),
            },
            {
              icon: window.sureFeedbackAdmin?.docs,
              title: __("Keeps Records", "surefeedback"),
              desc: __(
                "SureFeedback records all feedback, simplifying referencing and tracking revisions.",
                "surefeedback"
              ),
            },
          ].map(({ icon, title, desc }, idx) => (
            <Card key={idx} className="bg-background shadow-sm border-border p-4">
              <div className="flex items-start gap-3">
                <img src={icon || ""} alt={title} className="w-8 h-8" />
                <div>
                  <h3 className="text-base font-semibold text-foreground">{title}</h3>
                  <p className="text-sm text-muted-foreground mt-1">{desc}</p>
                </div>
              </div>
            </Card>
          ))}
        </CardContent>
      </Card>

      <Separator className="my-12 w-full max-w-5xl" />

      {/* Footer Section */}
      <div className="flex flex-col items-center text-center px-4">
        <h2 className="text-xl font-semibold text-foreground max-w-sm">
          {__("One Dashboard. Unlimited Websites. Any Platform.", "surefeedback")}
        </h2>
        <p className="mt-2 max-w-xl text-sm text-muted-foreground">
          {__(
            "Once installed on WordPress, SureFeedback works on unlimited websites, ANY platform. Central dashboard management included.",
            "surefeedback"
          )}
        </p>
        <img
          src={window.sureFeedbackAdmin?.footer || ""}
          alt={__("Footer Illustration", "surefeedback")}
          className="object-contain mt-8 w-[700px] h-[130px]"
        />

        <div className="mt-8 flex flex-col items-center gap-3">
          <Button
            size="default"
            onClick={handleGetStarted}
          >
            {__("Get Started Now", "surefeedback")}
            <ChevronRight className="ml-2 h-4 w-4" />
          </Button>
          <Button
            variant="link"
            onClick={() => (window.location.href = `${window.origin}/wp-admin`)}
          >
            {__("Go Back to the Dashboard", "surefeedback")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default Welcome;
