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

  // Custom task to copy environment file for staging
  grunt.registerTask("copy-env-staging", "Copy .env.staging to .env", function() {
    var fs = require('fs');
    if (fs.existsSync('.env.staging')) {
      fs.copyFileSync('.env.staging', '.env');
      grunt.log.writeln("Copied .env.staging to .env for staging release.");
    } else {
      grunt.log.warn(".env.staging file not found!");
    }
  });

  // Custom task to copy environment file for production
  grunt.registerTask("copy-env-production", "Copy .env.production to .env", function() {
    var fs = require('fs');
    if (fs.existsSync('.env.production')) {
      fs.copyFileSync('.env.production', '.env');
      grunt.log.writeln("Copied .env.production to .env for production release.");
    } else {
      grunt.log.warn(".env.production file not found!");
    }
  });

  // Custom task to restore original .env for development
  grunt.registerTask("restore-env-dev", "Restore .env for development", function() {
    grunt.log.writeln(".env kept as is for local development.");
  });

  grunt.registerTask("i18n", ["addtextdomain", "makepot"]);
  grunt.registerTask("readme", ["wp_readme_to_markdown"]);
  grunt.registerTask("build", ["composer-install", "build-assets", "i18n"]);

  // Release tasks for different environments
  grunt.registerTask("release:local", [
    "build",
    "clean:release_local",
    "restore-env-dev",
    "compress:local",
    "copy:release_local",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:staging", [
    "build",
    "clean:release_staging",
    "copy-env-staging",
    "compress:staging",
    "copy:release_staging",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:production", [
    "build",
    "clean:release_production",
    "copy-env-production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips"
  ]);

  grunt.registerTask("release:all", [
    "build",
    "clean:release_all",
    "restore-env-dev",
    "compress:local",
    "copy:release_local",
    "copy-env-staging",
    "compress:staging",
    "copy:release_staging",
    "copy-env-production",
    "compress:production",
    "copy:release_production",
    "clean:root_zips"
  ]);

  // Default release command creates all three zips
  grunt.registerTask("release", ["release:all"]);

  grunt.util.linefeed = "\n";
};
