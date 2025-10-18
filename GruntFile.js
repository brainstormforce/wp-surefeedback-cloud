module.exports = function (grunt) {
  "use strict";

  // Project configuration
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),

    // Read version from main plugin file
    version: (function() {
      var phpFile = grunt.file.read('surefeedback.php');
      var versionMatch = phpFile.match(/\* Version:\s*([0-9.]+)/);
      return versionMatch ? versionMatch[1] : '1.0.0';
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

    makepot: {
      target: {
        options: {
          domainPath: "/languages",
          exclude: [".git/*", "bin/*", "node_modules/*", "tests/*"],
          mainFile: "surefeedback.php",
          potFilename: "surefeedback.pot",
          potHeaders: {
            poedit: true,
            "x-poedit-keywordslist": true,
          },
          type: "wp-plugin",
          updateTimestamp: true,
        },
      },
    },

    compress: {
      // Local/Development zip
      local: {
        options: {
          archive: "surefeedback-v<%= version %>-local.zip",
        },
        files: [
          {
            src: [
              "**/*",
              ".env", // Explicitly include .env file (it's in .gitignore)
              "!node_modules/**",
              "!tests/**",
              "!.git/**",
              "!.gitignore",
              "!.claude/**",
              "!bin/**",
              "!src/**",
              "!resources/assets/**",
              "!release/**",
              "!vite.config.js",
              "!postcss.config.cjs",
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!composer.json",
              "!composer.lock",
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon",
              "!phpunit.xml",
              "!stubs-generator.php",
              "!jsconfig.json",
              "!components.json",
              "!GruntFile.js",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              "!README.md",
              "!.env.example",
              "!*.log",
              "!*.tmp",
              "!.DS_Store",
              "!Thumbs.db",
              "!.phpunit.cache/**",
              "!surefeedback-*.zip",
              "!surefeedback.zip",
            ],
            dest: "surefeedback/",
          },
        ],
      },
      // Staging zip
      staging: {
        options: {
          archive: "surefeedback-v<%= version %>-staging.zip",
        },
        files: [
          {
            src: [
              "**/*",
              ".env", // Explicitly include .env file (it's in .gitignore)
              "!node_modules/**",
              "!tests/**",
              "!.git/**",
              "!.gitignore",
              "!.claude/**",
              "!bin/**",
              "!src/**",
              "!resources/assets/**",
              "!release/**",
              "!vite.config.js",
              "!postcss.config.cjs",
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!composer.json",
              "!composer.lock",
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon",
              "!phpunit.xml",
              "!stubs-generator.php",
              "!jsconfig.json",
              "!components.json",
              "!GruntFile.js",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              "!README.md",
              "!.env.example",
              "!*.log",
              "!*.tmp",
              "!.DS_Store",
              "!Thumbs.db",
              "!.phpunit.cache/**",
              "!surefeedback-*.zip",
              "!surefeedback.zip",
            ],
            dest: "surefeedback/",
          },
        ],
      },
      // Production zip (WordPress.org release)
      production: {
        options: {
          archive: "surefeedback-v<%= version %>.zip",
        },
        files: [
          {
            src: [
              "**/*",
              "!node_modules/**",
              "!tests/**",
              "!.git/**",
              "!.gitignore",
              "!.claude/**",
              "!bin/**",
              "!src/**",
              "!resources/assets/**",
              "!release/**",
              "!vite.config.js",
              "!postcss.config.cjs",
              "!tailwind.config.cjs",
              "!package.json",
              "!package-lock.json",
              "!composer.json",
              "!composer.lock",
              "!phpcs.xml.dist",
              "!phpstan.neon",
              "!phpstan-baseline.neon",
              "!phpunit.xml",
              "!stubs-generator.php",
              "!jsconfig.json",
              "!components.json",
              "!GruntFile.js",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              "!README.md",
              "!.env.example",
              "!*.log",
              "!*.tmp",
              "!.DS_Store",
              "!Thumbs.db",
              "!.phpunit.cache/**",
              "!surefeedback-*.zip",
              "!surefeedback.zip",
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
            src: "surefeedback-v<%= version %>.zip",
            dest: "release/production/surefeedback-v<%= version %>.zip",
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
    var build = spawn('npm', ['run', 'build'], { stdio: 'inherit' });
    build.on('close', function(code) {
      if (code !== 0) {
        grunt.fail.fatal('Vite build failed with code ' + code);
      }
      grunt.log.writeln("Vite assets built successfully.");
      done();
    });
  });

  // Custom task to create .env file for local environment
  grunt.registerTask("env-local", "Create .env file for local environment", function() {
    var fs = require('fs');
    var envContent = '# SureFeedback Environment Configuration\n' +
                     '# Local/Development Environment\n\n' +
                     '# Active Environment (development, staging, production)\n' +
                     'ACTIVE_ENV=development\n\n' +
                     '# Development Environment\n' +
                     'SUREFEEDBACK_ENV_DEVELOPMENT=development\n' +
                     'SUREFEEDBACK_APP_URL_DEVELOPMENT=http://localhost:3000\n' +
                     'SUREFEEDBACK_API_URL_DEVELOPMENT=http://localhost:8000/api/v1\n\n' +
                     '# Staging Environment\n' +
                     'SUREFEEDBACK_ENV_STAGING=staging\n' +
                     'SUREFEEDBACK_APP_URL_STAGING=https://app-staging.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_STAGING=https://api-staging.surefeedback.com/api/v1\n\n' +
                     '# Production Environment\n' +
                     'SUREFEEDBACK_ENV_PRODUCTION=production\n' +
                     'SUREFEEDBACK_APP_URL_PRODUCTION=https://app.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_PRODUCTION=https://api.surefeedback.com/api/v1\n';
    fs.writeFileSync('.env', envContent);
    grunt.log.writeln(".env file created for local environment.");
  });

  // Custom task to create .env file for staging environment
  grunt.registerTask("env-staging", "Create .env file for staging environment", function() {
    var fs = require('fs');
    var envContent = '# SureFeedback Environment Configuration\n' +
                     '# Staging Environment\n\n' +
                     '# Active Environment (development, staging, production)\n' +
                     'ACTIVE_ENV=staging\n\n' +
                     '# Development Environment\n' +
                     'SUREFEEDBACK_ENV_DEVELOPMENT=development\n' +
                     'SUREFEEDBACK_APP_URL_DEVELOPMENT=http://localhost:3000\n' +
                     'SUREFEEDBACK_API_URL_DEVELOPMENT=http://localhost:8000/api/v1\n\n' +
                     '# Staging Environment\n' +
                     'SUREFEEDBACK_ENV_STAGING=staging\n' +
                     'SUREFEEDBACK_APP_URL_STAGING=https://app-staging.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_STAGING=https://api-staging.surefeedback.com/api/v1\n\n' +
                     '# Production Environment\n' +
                     'SUREFEEDBACK_ENV_PRODUCTION=production\n' +
                     'SUREFEEDBACK_APP_URL_PRODUCTION=https://app.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_PRODUCTION=https://api.surefeedback.com/api/v1\n';
    fs.writeFileSync('.env', envContent);
    grunt.log.writeln(".env file created for staging environment.");
  });

  // Custom task to create .env file for production environment
  grunt.registerTask("env-production", "Create .env file for production environment", function() {
    var fs = require('fs');
    var envContent = '# SureFeedback Environment Configuration\n' +
                     '# Production Environment\n\n' +
                     '# Active Environment (development, staging, production)\n' +
                     'ACTIVE_ENV=production\n\n' +
                     '# Development Environment\n' +
                     'SUREFEEDBACK_ENV_DEVELOPMENT=development\n' +
                     'SUREFEEDBACK_APP_URL_DEVELOPMENT=http://localhost:3000\n' +
                     'SUREFEEDBACK_API_URL_DEVELOPMENT=http://localhost:8000/api/v1\n\n' +
                     '# Staging Environment\n' +
                     'SUREFEEDBACK_ENV_STAGING=staging\n' +
                     'SUREFEEDBACK_APP_URL_STAGING=https://app-staging.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_STAGING=https://api-staging.surefeedback.com/api/v1\n\n' +
                     '# Production Environment\n' +
                     'SUREFEEDBACK_ENV_PRODUCTION=production\n' +
                     'SUREFEEDBACK_APP_URL_PRODUCTION=https://app.surefeedback.com\n' +
                     'SUREFEEDBACK_API_URL_PRODUCTION=https://api.surefeedback.com/api/v1\n';
    fs.writeFileSync('.env', envContent);
    grunt.log.writeln(".env file created for production environment.");
  });

  grunt.registerTask("i18n", ["addtextdomain", "makepot"]);
  grunt.registerTask("readme", ["wp_readme_to_markdown"]);
  grunt.registerTask("build", ["composer-install", "build-assets", "i18n"]);

  // Release tasks for different environments
  grunt.registerTask("release:local", [
    "build",
    "clean:release_local",
    "env-local",
    "compress:local",
    "copy:release_local",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:staging", [
    "build",
    "clean:release_staging",
    "env-staging",
    "compress:staging",
    "copy:release_staging",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:production", [
    "build",
    "clean:release_production",
    "env-production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:all", [
    "build",
    "clean:release_all",
    "env-local",
    "compress:local",
    "copy:release_local",
    "env-staging",
    "compress:staging",
    "copy:release_staging",
    "env-production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips"
  ]);

  // Default release command creates all three zips
  grunt.registerTask("release", ["release:all"]);

  grunt.util.linefeed = "\n";
};
