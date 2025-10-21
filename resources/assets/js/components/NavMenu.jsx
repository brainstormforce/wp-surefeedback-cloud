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
import { CircleHelp, FileText, Headset, User, Rocket, Link, Settings, Sliders } from "lucide-react";
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
    { label: __("Setup", "surefeedback"), path: "setup", icon: Rocket, showWhenConnected: false },
    { label: __("Connections", "surefeedback"), path: "connections", icon: Link, showWhenConnected: true },
    { label: __("Settings", "surefeedback"), path: "settings", icon: Settings, showWhenConnected: true },
    { label: __("Widget Control", "surefeedback"), path: "widget-control", icon: Sliders, showWhenConnected: true },
  ];

  const navItems = allNavItems.filter(item =>
    isConnected ? item.showWhenConnected : true
  );

  return (
    <div
      className="surefeedback-nav-menu w-full px-6 py-3 grid grid-cols-3 items-center bg-white border-b border-gray-200"
      style={{ zIndex: 9 }}
    >
      {/* Left: Logo */}
      <div className="flex items-center justify-start min-w-0">
        <NavLink to="setup" className="focus:outline-none flex-shrink-0">
          <img
            src={window.sureFeedbackAdmin?.surefeedback_icon || window.sureFeedbackAdmin?.pluginUrl + 'assets/images/settings/surefeedback.svg'}
            alt="SureFeedback"
            className="h-[25px] w-auto cursor-pointer focus:outline-none"
          />
        </NavLink>
      </div>

      {/* Center: Navigation Tabs */}
      <div className="flex items-center justify-center min-w-0">
        <NavigationMenu>
          <NavigationMenuList className="flex gap-4 justify-center">
            {navItems.map(({ label, path, icon: Icon }) => (
              <NavigationMenuItem key={path}>
                <NavLink to={path} className="focus:outline-none">
                  <NavigationMenuLink
                    className={cn(
                      "px-2 py-1.5 text-sm font-medium transition-colors border-b-2 focus:outline-none focus-visible:outline-none whitespace-nowrap flex items-center gap-1.5",
                      isActive(path)
                        ? "text-gray-900 border-[#6005FF]"
                        : "text-gray-600 border-transparent hover:text-gray-900 hover:border-gray-300"
                    )}
                  >
                    <Icon className="w-4 h-4" />
                    {label}
                  </NavigationMenuLink>
                </NavLink>
              </NavigationMenuItem>
            ))}
          </NavigationMenuList>
        </NavigationMenu>
      </div>

      {/* Right: Actions */}
      <div className="flex items-center justify-end gap-3 min-w-0">
        {/* Plan Badge */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Badge
              variant="secondary"
              className="cursor-pointer select-none flex-shrink-0"
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
            <CircleHelp className="cursor-pointer flex-shrink-0 w-5 h-5" />
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
          <User className="cursor-pointer text-gray-900 focus:outline-none flex-shrink-0 w-5 h-5" />
        </NavLink>
      </div>
    </div>
  );
};

export default NavMenu;
