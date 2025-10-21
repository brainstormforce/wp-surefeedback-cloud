import React, { useEffect, useState } from "react";
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
} from "@/components/ui/navigation-menu";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuLabel,
} from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { CircleHelp, FileText, Headset, User } from "lucide-react";
import { __ } from "@wordpress/i18n";
import { NavLink, useRouter } from "@/utils/Router";

const NavMenu = () => {
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const { currentRoute } = useRouter();

  const isActive = (path) => currentRoute === path;

  const handleRedirect = (url) => {
    window.open(url, "_blank");
    setIsDropdownOpen(false);
  };

  // Check if website is connected
  const isConnected = window.sureFeedbackAdmin?.connection?.connected || false;

  // Filter nav items based on connection status
  const allNavItems = [
    { label: __("Setup", "surefeedback"), path: "setup", showWhenConnected: false },
    { label: __("Connections", "surefeedback"), path: "connections", showWhenConnected: true },
    { label: __("Settings", "surefeedback"), path: "settings", showWhenConnected: true },
    { label: __("Widget Control", "surefeedback"), path: "widget-control", showWhenConnected: true },
  ];

  const navItems = allNavItems.filter(item =>
    isConnected ? item.showWhenConnected : true
  );

  return (
    <div
      className="surefeedback-nav-menu w-full px-6 py-4 grid grid-cols-3 items-center bg-white border-b"
      style={{ zIndex: 9 }}
    >
      {/* Left: Logo */}
      <div className="flex items-center justify-start">
        <NavLink to="setup" className="focus:outline-none">
          <img
            src={window.sureFeedbackAdmin?.surefeedback_icon || window.sureFeedbackAdmin?.pluginUrl + 'assets/images/settings/surefeedback.svg'}
            alt="SureFeedback"
            className="h-[25px] w-22 cursor-pointer focus:outline-none"
          />
        </NavLink>
      </div>

      {/* Center: Navigation Tabs */}
      <div className="flex items-center justify-center">
        <NavigationMenu>
          <NavigationMenuList className="flex gap-6">
            {navItems.map(({ label, path }) => (
              <NavigationMenuItem key={path}>
                <NavLink to={path} className="focus:outline-none">
                  <NavigationMenuLink
                    className={cn(
                      "px-3 py-2 text-sm font-medium transition-colors border-b-2 focus:outline-none focus-visible:outline-none",
                      isActive(path)
                        ? "text-black border-[#6005FF]"
                        : "text-gray-500 border-transparent hover:text-gray-900"
                    )}
                  >
                    {label}
                  </NavigationMenuLink>
                </NavLink>
              </NavigationMenuItem>
            ))}
          </NavigationMenuList>
        </NavigationMenu>
      </div>

      {/* Right: Actions */}
      <div className="flex items-center justify-end gap-4">
        {/* Plan Badge */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Badge
              variant="secondary"
              className="cursor-pointer select-none"
            >
              {__("Free", "surefeedback")}
            </Badge>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-48">
            <DropdownMenuItem>
              {__("Version", "surefeedback")}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        {/* Help Dropdown */}
        <DropdownMenu open={isDropdownOpen} onOpenChange={setIsDropdownOpen}>
          <DropdownMenuTrigger asChild>
            <CircleHelp className="cursor-pointer" />
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-64 bg-white">
            <DropdownMenuLabel>
              {__("Useful Resources", "surefeedback")}
            </DropdownMenuLabel>
            {[
              {
                label: __("Getting Started", "surefeedback"),
                url: "https://ultimateelementor.com/docs/getting-started-with-ultimate-addons-for-elementor-lite/",
                icon: <FileText />,
              },
              {
                label: __("How to use widgets", "surefeedback"),
                url: "https://ultimateelementor.com/docs-category/widgets/",
                icon: <FileText />,
              },
              {
                label: __("How to use features", "surefeedback"),
                url: "https://ultimateelementor.com/docs-category/features/",
                icon: <FileText />,
              },
              {
                label: __("How to use templates", "surefeedback"),
                url: "https://ultimateelementor.com/docs-category/templates/",
                icon: <FileText />,
              },
              {
                label: __("Contact us", "surefeedback"),
                url: "https://ultimateelementor.com/contact/",
                icon: <Headset />,
              },
            ].map(({ label, url, icon }) => (
              <DropdownMenuItem
                key={label}
                onClick={() => handleRedirect(url)}
                className="flex items-center gap-2 text-gray-800 cursor-pointer"
              >
                {icon}
                {label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        {/* User Icon */}
        <NavLink to="settings" className="focus:outline-none">
          <User className="cursor-pointer text-black focus:outline-none" />
        </NavLink>
      </div>
    </div>
  );
};

export default NavMenu;
