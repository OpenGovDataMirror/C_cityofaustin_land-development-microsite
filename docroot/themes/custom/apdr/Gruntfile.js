module.exports = function (grunt) {
  const sass = require('node-sass');

  require('load-grunt-tasks')(grunt);

  grunt.initConfig({
    sass: {
      options: {
        sourceMap: true,
        outputStyle: 'expanded',
        sourceComments: true,
        implementation: sass
      },
      dist: {
        files: {
          'css/style.css': 'scss/style.scss'
        }
      }
    },
    watch: {
      scripts: {
        files: [
            'scss/*.scss',
            'scss/*/*.scss'
        ],
        tasks: ['sass'],
      }
    },
  });

  grunt.registerTask('default', ['sass']);
};
