module.exports = function (grunt) {
  "use strict";

  // Project configuration
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),

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
      main: {
        options: {
          archive: "surefeedback.zip",
        },
        files: [
          {
            src: [
              "**/*",
              "!node_modules/**",
              "!tests/**",
              "!.git/**",
              "!.gitignore",
              "!bin/**",
              "!vendor/**",
              "!src/**",
              "!resources/assets/**",
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
              "!stubs-generator.php",
              "!GruntFile.js",
              "!CLAUDE.md",
              "!GithubCopilot.md",
              "!README.md",
              "!*.log",
              "!*.tmp",
              "!.DS_Store",
              "!Thumbs.db",
            ],
            dest: "surefeedback/",
          },
        ],
      },
    },
  });

  grunt.loadNpmTasks("grunt-wp-i18n");
  grunt.loadNpmTasks("grunt-wp-readme-to-markdown");
  grunt.loadNpmTasks("grunt-contrib-compress");

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

  grunt.registerTask("i18n", ["addtextdomain", "makepot"]);
  grunt.registerTask("readme", ["wp_readme_to_markdown"]);
  grunt.registerTask("build", ["build-assets", "i18n"]);
  grunt.registerTask("release", ["build", "compress"]);

  grunt.util.linefeed = "\n";
};
