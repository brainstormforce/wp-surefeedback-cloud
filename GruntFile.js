module.exports = function (grunt) {
  "use strict";

  // Project configuration
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),

    // Read version from main plugin file
    version: (function() {
      var phpFile = grunt.file.read('surefeedback.php');
      var versionMatch = phpFile.match(/\* Version:\s*([0-9.]+)/);
      return versionMatch ? versionMatch[1] : '0.0.1';
    })(),

    addtextdomain: {
      options: {
        textdomain: "surefeedback",
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
        command: 'wp i18n make-pot . languages/surefeedback.pot --domain=surefeedback --include="*.php,*.js,*.jsx" --exclude="node_modules,tests,vendor/*/tests,vendor/*/test"',
        options: {
          stderr: false
        }
      }
    },

    compress: {
      // Local/Development zip
      local: {
        options: {
          archive: "surefeedback-v<%= version %>-local.zip",
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
              "!bin/**", 
              "!src/**",
              "!resources/**",
              "!database/**",
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
              "!vite.config.js",
              "!postcss.config.cjs", 
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!tsconfig.json",
              "!components.json",
              "!GruntFile.js",
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
              "!*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
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
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback/",
          },
        ],
      },
      // Staging zip
      staging: {
        options: {
          archive: "surefeedback-v<%= version %>-staging.zip",
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
              "!bin/**", 
              "!src/**",
              "!resources/**",
              "!database/**",
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
              "!vite.config.js",
              "!postcss.config.cjs", 
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!tsconfig.json",
              "!components.json",
              "!GruntFile.js",
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
              "!*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
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
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback/",
          },
        ],
      },
      // Production zip (WordPress.org release)
      production: {
        options: {
          archive: "surefeedback.<%= version %>.zip",
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
              "!bin/**", 
              "!src/**",
              "!resources/**",
              "!database/**",
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
              "!vite.config.js",
              "!postcss.config.cjs", 
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!pnpm-lock.yaml",
              "!yarn.lock",
              "!composer.lock",
              "!jsconfig.json",
              "!tsconfig.json",
              "!components.json",
              "!GruntFile.js",
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
              "!*.map",
              "!*.dev.*",
              // IDE and editor files
              "!.vscode/**",
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
              "!webpack.config.*",
              "!rollup.config.*",
              "!*.config.js",
              "!*.config.cjs",
              "!*.config.mjs",
              "!*.config.ts",
              // Include complete vendor folder (no exclusions)
            ],
            dest: "surefeedback/",
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
      root_zips: ["surefeedback*.zip"],
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
            src: "surefeedback-v<%= version %>-local.zip",
            dest: "release/local/surefeedback-v<%= version %>-local.zip",
          },
        ],
      },
      release_staging: {
        files: [
          {
            src: "surefeedback-v<%= version %>-staging.zip",
            dest: "release/staging/surefeedback-v<%= version %>-staging.zip",
          },
        ],
      },
      release_production: {
        files: [
          {
            src: "surefeedback.<%= version %>.zip",
            dest: "release/production/surefeedback.<%= version %>.zip",
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

  // Custom task to build Vite assets
  grunt.registerTask("build-assets", "Build Vite assets", function() {
    var done = this.async();
    var spawn = require('child_process').spawn;
    grunt.log.writeln("Building Vite assets...");
    
    // Check if npm exists, fallback to pnpm or yarn
    var buildCommand = 'npm';
    var buildArgs = ['run', 'build'];
    
    var build = spawn(buildCommand, buildArgs, { stdio: 'inherit' });
    build.on('close', function(code) {
      if (code !== 0) {
        grunt.fail.fatal('Vite build failed with code ' + code);
      }
      grunt.log.writeln("Vite assets built successfully.");
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

  // Note: Environment configuration uses WordPress constants (Sigmize pattern)
  // - Constants defined in surefeedback.php with production defaults
  // - Can be overridden in wp-config.php per environment
  // - No .env files needed - WordPress.org compliant

  grunt.registerTask("i18n", ["addtextdomain", "shell:makepot"]);
  grunt.registerTask("makepot", ["shell:makepot"]); // Alias for backward compatibility
  grunt.registerTask("readme", ["wp_readme_to_markdown"]);
  grunt.registerTask("build", ["clean:dev_artifacts", "composer-install", "build-assets", "i18n"]);

  // Release tasks for different environments
  // Note: Using simple constants pattern (Sigmize style)
  // - Constants defined in surefeedback.php with production defaults
  // - Users can override in wp-config.php if needed
  // - No auto-detection, no .env files - WordPress.org compliant
  grunt.registerTask("release:local", [
    "build",
    "remove-empty-dirs",
    "clean:release_local",
    "compress:local",
    "copy:release_local",
    "clean:root_zips",
    "clean:node_modules"
  ]);

  grunt.registerTask("release:staging", [
    "build",
    "remove-empty-dirs",
    "clean:release_staging",
    "compress:staging",
    "copy:release_staging",
    "clean:root_zips",
    "clean:node_modules"
  ]);

  grunt.registerTask("release:production", [
    "build",
    "remove-empty-dirs",
    "clean:release_production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips",
    "clean:node_modules"
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
    "clean:node_modules"
  ]);

  // Default release command creates all three zips
  grunt.registerTask("release", ["release:all"]);

  grunt.util.linefeed = "\n";
};
