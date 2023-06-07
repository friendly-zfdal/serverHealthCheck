# ServerHealthCheck plugin for GLPI

## Introduction

This plugin, when using the ipmitool library, queries the organization's servers using
the sdr command and draws a conclusion about their current state. The data collected
in the plugin table can be presented in three types: public/private reports and widget on the GLPI dashboard.

## Documentation

There are three types of documentation which are available [here](https://github.com/friendly-zfdal/serverHealthCheck/blob/main/Docs)
on two languages (Russian and English):
1) Source Code documentation;
2) Usage guide for administrator;
3) Usage guide for user.

## Installation

```sh
cd /my/glpi/deployment/main/directory/plugins
git clone https://github.com/friendly-zfdal/serverHealthCheck.git serverhealthcheck