# Auto Node Translate

This module provides the ability to add automatic translations to nodes
using external libraries.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/auto_node_translate).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/auto_node_translate).


## Table of contents

- Requirements
- Installation
- Features
- Configuration
- Maintainers


## Requirements

This module requires the the google api client if or don't use composer to 
install the module install it with composer.

- `composer require google/cloud-translate:^1.10`

If using Amazon Translate, install the aws-sdk-php module with composer.

- `composer require aws/aws-sdk-php`


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Features

At the moment the module provides 1 Translation API:
- MyMemory 
  `https://mymemory.translated.net/`
Other translation apis can be added with contrib modules.

## Configuration

- MyMemory 
  Configure email in /admin/config/regional/my-memory to 
  increase the number of words to translate by the api. 
    
## Maintainers

- Paulo Calado - [kallado](https://www.drupal.org/u/kallado)
- João Mauricio - [jmauricio](https://www.drupal.org/u/jmauricio)

This project has been sponsored by:
- Visit: [Javali](https://www.javali.pt) for more information
