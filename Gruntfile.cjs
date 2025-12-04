module.exports = function (grunt) {
  "use strict";

  // Project configuration
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),

    // Read version from main plugin file
    version: (function() {
      var phpFile = grunt.file.read('surefeedback-cloud.php');
      var versionMatch = phpFile.match(/\* Version:\s*([0-9.]+)/);
      return versionMatch ? versionMatch[1] : '0.0.1';
    })(),

    addtextdomain: {
      options: {
        textdomain: "surefeedback-cloud",
      },
      update_all_domains: {
        options: {
          updateDomains: true,
        },
        src: [
          "*.php",
          "**/*.php",
          "!.git/**/*",
          "!bin/**/*",
          "!node_modules/**/*",
          "!tests/**/*",
        ],
      },
    },

    wp_readme_to_markdown: {
      your_target: {
        files: {
          "README.md": "readme.txt",
        },
      },
    },

    // Custom WP-CLI task for better JavaScript/JSX support
    shell: {
      makepot: {
        command: 'php -d memory_limit=512M $(which wp) i18n make-pot . languages/surefeedback-cloud.pot --domain=surefeedback-cloud --include="*.php" --exclude="node_modules,tests,vendor,assets/js"',
        options: {
          stderr: false,
          failOnError: false
        }
      }
    },

    compress: {
      // Local/Development zip
      local: {
        options: {
          archive: "surefeedback-cloud-local.zip",
          mode: 'zip',
          level: 9,
        },
        files: [
          {
            src: [
              "**/*",
              // Exclude node_modules at any level (comprehensive patterns)
              "!node_modules",
              "!node_modules/**", 
              "!**/node_modules",
              "!**/node_modules/**",
              "!**/*node_modules*",
              "!**/*node_modules*/**",
              // Development and build files
              "!tests/**",
              "!src/**",
              "!release/**",
              // Git and version control
              "!.git/**",
              "!.github/**",
              "!.gitignore",
              "!.gitattributes",
              // Claude AI and documentation
              "!.claude/**",
              "!*.md",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              // WordPress.org assets (not needed in production)
              "!.wordpress-org/**",
              "!.distignore",
              // Build and config files
              "!webpack.config.js",
              "!postcss.config.cjs",
              "!postcss.config.js",
              "!tailwind.config.cjs",
              "!tailwind.config.ts",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!components.json",
              "!Gruntfile.cjs",
              // Code quality and testing
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon", 
              "!phpunit.xml",
              "!stubs-generator.php",
              "!*.test.*",
              "!*.spec.*",
              "!__tests__/**",
              "!coverage/**",
              "!.phpunit.cache/**",
              // Environment and logs
              "!.env*",
              "!*.log",
              "!*.tmp",
              // OS files
              "!.DS_Store",
              "!Thumbs.db",
              // Generated zip files
              "!surefeedback-*.zip",
              "!surefeedback.*.zip",
              "!surefeedback.zip",
              // Source maps and dev files
              "!**/*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
              "!.cursor/**",
              "!.idea/**", 
              "!*.sublime-*",
              "!.editorconfig",
              // Linting and formatting
              "!.eslintrc*",
              "!.prettierrc*",
              "!.stylelintrc*",
              // Build tools config
              "!babel.config.*",
              "!.babelrc*",
              "!eslint.config.*",
              "!prettier.config.*",
              "!jest.config.*",
              "!vitest.config.*",
              "!vite.config.*",
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback-cloud/",
          },
        ],
      },
      // Staging zip
      staging: {
        options: {
          archive: "surefeedback-cloud-staging.zip",
          mode: 'zip',
          level: 9,
        },
        files: [
          {
            src: [
              "**/*",
              // Exclude node_modules at any level (comprehensive patterns)
              "!node_modules",
              "!node_modules/**", 
              "!**/node_modules",
              "!**/node_modules/**",
              "!**/*node_modules*",
              "!**/*node_modules*/**",
              // Development and build files
              "!tests/**",
              "!src/**",
              "!release/**",
              // Git and version control
              "!.git/**",
              "!.github/**",
              "!.gitignore",
              "!.gitattributes",
              // Claude AI and documentation
              "!.claude/**",
              "!*.md",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              // WordPress.org assets (not needed in production)
              "!.wordpress-org/**",
              "!.distignore",
              // Build and config files
              "!webpack.config.js",
              "!postcss.config.cjs",
              "!postcss.config.js",
              "!tailwind.config.cjs",
              "!tailwind.config.ts",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!components.json",
              "!Gruntfile.cjs",
              // Code quality and testing
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon", 
              "!phpunit.xml",
              "!stubs-generator.php",
              "!*.test.*",
              "!*.spec.*",
              "!__tests__/**",
              "!coverage/**",
              "!.phpunit.cache/**",
              // Environment and logs
              "!.env*",
              "!*.log",
              "!*.tmp",
              // OS files
              "!.DS_Store",
              "!Thumbs.db",
              // Generated zip files
              "!surefeedback-*.zip",
              "!surefeedback.*.zip",
              "!surefeedback.zip",
              // Source maps and dev files
              "!**/*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
              "!.cursor/**",
              "!.idea/**", 
              "!*.sublime-*",
              "!.editorconfig",
              // Linting and formatting
              "!.eslintrc*",
              "!.prettierrc*",
              "!.stylelintrc*",
              // Build tools config
              "!babel.config.*",
              "!.babelrc*",
              "!eslint.config.*",
              "!prettier.config.*",
              "!jest.config.*",
              "!vitest.config.*",
              "!vite.config.*",
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback-cloud/",
          },
        ],
      },
      // Production zip (WordPress.org release)
      production: {
        options: {
          archive: "surefeedback-cloud.<%= version %>.zip",
          mode: 'zip',
          level: 9,
        },
        files: [
          {
            expand: true,
            filter: function(filepath) {
              // Exclude node_modules at any level (comprehensive patterns)
              return !filepath.match(/node_modules/) && 
                     !filepath.includes('node_modules') &&
                     !filepath.match(/\/node_modules\//) &&
                     !filepath.match(/\\node_modules\\/);
            },
            src: [
              "**/*",
              // Exclude node_modules at any level (comprehensive patterns)
              "!node_modules",
              "!node_modules/**", 
              "!**/node_modules",
              "!**/node_modules/**",
              "!**/*node_modules*",
              "!**/*node_modules*/**",
              // Development and build files
              "!tests/**",
              "!src/**",
              "!release/**",
              // Git and version control
              "!.git/**",
              "!.github/**",
              "!.gitignore",
              "!.gitattributes",
              // Claude AI and documentation
              "!.claude/**",
              "!*.md",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              // WordPress.org assets (keep for production)
              "!.distignore",
              // Build and config files
              "!webpack.config.js",
              "!postcss.config.cjs",
              "!postcss.config.js",
              "!tailwind.config.cjs",
              "!tailwind.config.ts",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!components.json",
              "!Gruntfile.cjs",
              // Code quality and testing
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon", 
              "!phpunit.xml",
              "!stubs-generator.php",
              "!*.test.*",
              "!*.spec.*",
              "!__tests__/**",
              "!coverage/**",
              "!.phpunit.cache/**",
              // Environment and logs
              "!.env*",
              "!*.log",
              "!*.tmp",
              // OS files
              "!.DS_Store",
              "!Thumbs.db",
              // Generated zip files
              "!surefeedback-*.zip",
              "!surefeedback.*.zip",
              "!surefeedback.zip",
              // Source maps and dev files (exclude source maps in production)
              "!**/*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
              "!.cursor/**",
              "!.idea/**", 
              "!*.sublime-*",
              "!.editorconfig",
              // Linting and formatting
              "!.eslintrc*",
              "!.prettierrc*",
              "!.stylelintrc*",
              // Build tools config
              "!babel.config.*",
              "!.babelrc*",
              "!eslint.config.*",
              "!prettier.config.*",
              "!jest.config.*",
              "!vitest.config.*",
              "!vite.config.*",
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback-cloud/",
          },
        ],
      },
    },

    clean: {
      // Clean release folders before creating new zips
      release_local: ["release/local/*.zip"],
      release_staging: ["release/staging/*.zip"],
      release_production: ["release/production/*.zip"],
      release_all: ["release/**/*.zip"],
      // Clean root-level zips
      root_zips: ["surefeedback-cloud*.zip"],
      // Clean development artifacts (only clean files that actually exist)
      dev_artifacts: [
        ".DS_Store",
        "Thumbs.db"
      ],
      // Clean node_modules to ensure it's not included
      node_modules: [
        "node_modules"
      ],
    },

    copy: {
      // Copy zips to release folders
      release_local: {
        files: [
          {
            src: "surefeedback-cloud-local.zip",
            dest: "release/local/surefeedback-cloud-local.zip",
          },
        ],
      },
      release_staging: {
        files: [
          {
            src: "surefeedback-cloud-staging.zip",
            dest: "release/staging/surefeedback-cloud-staging.zip",
          },
        ],
      },
      release_production: {
        files: [
          {
            src: "surefeedback-cloud.<%= version %>.zip",
            dest: "release/production/surefeedback-cloud.<%= version %>.zip",
          },
        ],
      },
    },
  });

  grunt.loadNpmTasks("grunt-wp-i18n");
  grunt.loadNpmTasks("grunt-wp-readme-to-markdown");
  grunt.loadNpmTasks("grunt-contrib-compress");
  grunt.loadNpmTasks("grunt-contrib-clean");
  grunt.loadNpmTasks("grunt-contrib-copy");
  grunt.loadNpmTasks("grunt-shell");

  // Custom task to install production composer dependencies
  grunt.registerTask("composer-install", "Install production Composer dependencies", function() {
    var done = this.async();
    var spawn = require('child_process').spawn;
    grunt.log.writeln("Installing production Composer dependencies...");
    var composer = spawn('composer', ['install', '--no-dev', '--optimize-autoloader'], { stdio: 'inherit' });
    composer.on('close', function(code) {
      if (code !== 0) {
        grunt.fail.fatal('Composer install failed with code ' + code);
      }
      grunt.log.writeln("Composer dependencies installed successfully.");
      done();
    });
  });

  // Custom task to build Webpack assets (JavaScript and CSS)
  grunt.registerTask("build-assets", "Build Webpack assets (JS and CSS)", function() {
    var done = this.async();
    var spawn = require('child_process').spawn;
    grunt.log.writeln("Building Webpack assets...");
    
    // Run npm build command which builds both JS and CSS via webpack
    var buildCommand = 'npm';
    var buildArgs = ['run', 'build'];
    
    var build = spawn(buildCommand, buildArgs, { stdio: 'inherit' });
    build.on('close', function(code) {
      if (code !== 0) {
        grunt.fail.fatal('Webpack build failed with code ' + code);
      }
      grunt.log.writeln("Webpack assets built successfully.");
      grunt.log.writeln("  - JavaScript: assets/js/admin.js");
      grunt.log.writeln("  - CSS: assets/dist/admin.css");
      done();
    });
    
    build.on('error', function(err) {
      grunt.log.error('Build command failed:', err.message);
      grunt.fail.fatal('Could not execute build command');
    });
  });

  // Custom task to remove empty directories
  grunt.registerTask("remove-empty-dirs", "Remove empty directories before compression", function() {
    var fs = require('fs');
    var path = require('path');
    
    function isEmptyDir(dirPath) {
      try {
        var files = fs.readdirSync(dirPath);
        if (files.length === 0) {
          return true;
        }
        // Check if all items are empty directories
        return files.every(function(file) {
          var filePath = path.join(dirPath, file);
          var stat = fs.statSync(filePath);
          if (stat.isDirectory()) {
            return isEmptyDir(filePath);
          }
          return false;
        });
      } catch (e) {
        return false;
      }
    }

    function removeEmptyDirs(dirPath, rootPath) {
      try {
        var files = fs.readdirSync(dirPath);
        files.forEach(function(file) {
          var filePath = path.join(dirPath, file);
          var stat = fs.statSync(filePath);
          if (stat.isDirectory()) {
            // Recursively check subdirectories
            removeEmptyDirs(filePath, rootPath);
            // Check if directory is now empty
            if (isEmptyDir(filePath)) {
              // Don't remove certain directories that should exist even if empty
              var relativePath = path.relative(rootPath, filePath);
              var shouldKeep = [
                'languages',
                'assets',
                'includes',
                'vendor',
              ].some(function(keepDir) {
                return relativePath.split(path.sep)[0] === keepDir;
              });
              
              if (!shouldKeep) {
                fs.rmdirSync(filePath);
                grunt.log.writeln("Removed empty directory: " + relativePath);
              }
            }
          }
        });
      } catch (e) {
        // Ignore errors for directories that don't exist or can't be read
      }
    }

    var rootPath = process.cwd();
    grunt.log.writeln("Removing empty directories...");
    removeEmptyDirs(rootPath, rootPath);
    grunt.log.writeln("Empty directories removed.");
  });



  grunt.registerTask("i18n", ["addtextdomain", "shell:makepot"]);
  grunt.registerTask("makepot", ["shell:makepot"]); // Alias for backward compatibility
  grunt.registerTask("readme", ["wp_readme_to_markdown"]);
  
  // Build task: Clean artifacts, install Composer deps, build Webpack assets (JS + CSS), generate i18n
  grunt.registerTask("build", ["clean:dev_artifacts", "composer-install", "build-assets", "i18n"]);

  // Release tasks for different environments
  // Build process:
  // 1. Clean development artifacts
  // 2. Install production Composer dependencies
  // 3. Build Webpack assets (JavaScript to assets/js/admin.js, CSS to assets/dist/admin.css)
  // 4. Generate i18n translations
  // 5. Remove empty directories
  // 6. Clean old release zips
  // 7. Create zip archive (excluding src/, node_modules/, build configs)
  // 8. Copy to release folder
  // 9. Clean root-level zip files
  grunt.registerTask("release:local", [
    "build",
    "remove-empty-dirs",
    "clean:release_local",
    "compress:local",
    "copy:release_local",
    "clean:root_zips",
  ]);

  grunt.registerTask("release:staging", [
    "build",
    "remove-empty-dirs",
    "clean:release_staging",
    "compress:staging",
    "copy:release_staging",
    "clean:root_zips",
  ]);

  grunt.registerTask("release:production", [
    "build",
    "remove-empty-dirs",
    "clean:release_production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips",
  ]);

  grunt.registerTask("release:all", [
    "build",
    "remove-empty-dirs",
    "clean:release_all",
    "compress:local",
    "copy:release_local",
    "compress:staging",
    "copy:release_staging",
    "compress:production",
    "copy:release_production",
    "clean:root_zips",
  ]);

  // Default release command creates all three zips
  grunt.registerTask("release", ["release:all"]);

  grunt.util.linefeed = "\n";
};
