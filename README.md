# Oxid Module Internals
Internal OXID eShop 6 module system information and troubleshooting tools.

proudly presented by [OXID Hackathon 2017](https://openspacer.org/12-oxid-community/185-oxid-hackathon-nuernberg-2017/) ;-)

Original module (for Oxid eShop 5.x/4.x) by [Alfonsas Cirtautas](https://github.com/acirtautas/oxid-module-internals).

## Features
[![Build Status](https://travis-ci.org/OXIDprojects/oxid-module-internals.svg?branch=master)](https://travis-ci.org/OXIDprojects/oxid-module-internals)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/d57c5d4c3f5047a99dbe23b34f0ef1df)](https://app.codacy.com/app/keywan.ghadami/oxid-module-internals?utm_source=github.com&utm_medium=referral&utm_content=OXIDprojects/oxid-module-internals&utm_campaign=Badge_Grade_Settings)

 * Display highlighted metadata file content.
 * Reset module related shop cache data.
 * Toggle module activation / deactivation
 * Compare and troubleshoot metadata vs internally stores data
   * Extended classes
   * Template blocks
   * Settings
   * Registered files
   * Registered templates
   * Version
   * Events
 * Console command to fix modules  

## Installation

```
composer require oxid-community/moduleinternals
```

## Run Codeception tests
First copy .env.dist to .env and set your credentials

### Run all tests
```
.Build/bin/codecept run
```

### Run all in a suite
```
.Build/bin/codecept run acceptance
```

### Params
--steps --html -vvv

Steps will show on cli
Html repourt as file
Verbose to see all information for debugging

## Screenshot

![OXID_moduleinternals](screenshot.png)

## Changelog
* 2019-01-21  2.0.0 Option to disable blocks,autodiscover module version number from composer,automatic module state fix (when opening admin module list) with feedback, overview page with accordion, admin homepage warnings, improved logging, improved fixing, remove state from disabled modules, support for different console versions
* 2018-12-12  1.5.2 avoid php warning if module namespace can not be found
* 2018-11-23  1.5.1 avoid error when fixing deactive module that has controllers
* 2018-11-23  1.5.0 do not scan deactivated modules
* 2018-11-23  1.4.3 fix error (modullist not shown, if oxid console is not installed)
* 2018-11-23  1.4.2 ** WARNING KNOWN BUG see 1.4.3 **
                    fix compatibility with console applications by supporting new command registration via services.yml
* 2018-11-23  1.4.1 add compatibility code for oxrun 
* 2018-11-22  1.4.0 improve performance, added module:fix console command, fixed module controller check 
* 2018-11-21  1.3.0 Show blocks, case sensitive file exist checks, support legacy class names, warn when extending edition namespace, highlight modules with issues.   
* 2018-11-21  1.2.2 fix fixing extensions
* 2018-11-14  1.2.1 migration support from 1.0.1
* 2018-11-14  1.2.0 support metadata v2.1
* 2018-09-13  1.1.0 add external module healthy status page
* 2017-12-15	1.0.1	namespace, docblocks
* 2017-12-09	1.0.0	module release


## Related Projects
[Oxid Console](https://github.com/OXIDprojects/oxid-console)
