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

  const navItems = [
    { label: __("Setup", "surefeedback"), path: "setup" },
    { label: __("Connections", "surefeedback"), path: "connections" },
    { label: __("Settings", "surefeedback"), path: "settings" },
  ];

  return (
    <div
      className="surefeedback-nav-menu w-full px-4 py-2 flex items-center justify-between bg-white border-b"
      style={{ zIndex: 9 }}
    >
      {/* Left: Logo */}
      <NavLink to="setup">
        <img
          src={window.sureFeedbackAdmin?.icon_url || ""}
          alt="SureFeedback"
          className="h-8 w-8 cursor-pointer"
        />
      </NavLink>

      {/* Center: Navigation Tabs */}
      <NavigationMenu>
        <NavigationMenuList className="flex gap-6">
          {navItems.map(({ label, path }) => (
            <NavigationMenuItem key={path}>
              <NavLink to={path}>
                <NavigationMenuLink
                  className={cn(
                    "px-3 py-2 text-sm font-medium transition-colors border-b-2",
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

      {/* Right: Actions */}
      <div className="flex items-center gap-4">
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
        <NavLink to="settings">
          <User className="cursor-pointer text-black" />
        </NavLink>
      </div>
    </div>
  );
};

export default NavMenu;
