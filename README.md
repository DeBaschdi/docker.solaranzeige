# docker.solaranzeige
<img src="https://raw.githubusercontent.com/DeBaschdi/solar_config/master/solaranzeige/splash.png" height="100" width="150">

This is the source code of Solaranzeige. 
Solaranzeige is a german word for "photovoltaic display". 
The reason for this tool is to extract all current data of a power inverter of a photovoltaic installation, store that information in a database and make it visible over time.

This information is only stored locally and is not exposed to the public or any kind of provider. So your independent from other companies or the Internet itself or cloud provider that may remove the service in the future.

The source of docker.solaranzeige comes from www.solaranzeige.de which is a huge german speaking bulletin board. Solaranzeige know a very long list of inverters and wallboxes and it uses OpenSource products like InfluxDB and Grafana to store data and make it visible.

Because the original code behind the project was very unstructured, I decided to rebuild the whole project more or less from scratch.

### Prerequisites

You will need to have `docker` installed on your system and the user you want to run it needs to be in the `docker` group. 
Also you should add the `compose` plugin to docker for you environemt.

> **Note:** The image is a multi-arch build providing variants for amd64, arm32v7 and arm64v8 and also, an legacy amd64 Build for old Synology Kernels.

You could ran the docker image on any hardware that supports this CPU architectures. Please feel free to build the image for your own environment.

## Installation

Checkout this repository or download it from Github (to retrieve the provisioning informations for the database.

Then copy the env_example.txt to `.env` and configure the options, you need (see below).

And then start the container with ´docker compose up -d`.

## Technical info for docker

To run the application, you need to configure it first. The application checks a lot of environment variables and some of them are mandatory.

| Parameter | Optional | Values/Type | Default | Description |
| ---- | --- | --- | --- | --- |
| `USER_ID` | yes | [integer] | 99 | UID to run Solaranzeige as |
| `GROUP_ID` | yes | [integer] | 100 | GID to run Solaranzeige as |
| `TIMEZONE` | yes | [string] | Europe/Berlin | Timezone for the container |
| `MOSQUITTO` | yes | yes, no | yes | Turn On / Off mosquitto service inside this Container |
| `SA_REGLER` | no | [integer] | 0 | The id of the used power inverter | 
| `SA_SERIENNUMMER` | no | [string] | - | pwer inverter serial number | 
| `SA_WR_IP` | no | [string] | - | power inverter IP | 
| `SA_WR_PORT` | no | [integer] | - | power inverter port | 
| `SA_OBJECT` | no | [string] | - | name of the environment | 
| `SA_INFLUX_HOST` | no | [string] | - | hostname of InfluxDB | 
| `SA_INFLUX_USERNAME` | no | [string] | - | username of InfluxDB | 
| `SA_INFLUX_PASSWORD` | no | [string] | - | password for InfluxDB | 

Because we use the docker compose environment, its very easy to configure everything, just extend it with the parameter of your environment.


To learn how to manually start the container or about available parameters (you might need for your GUI used) see the following example:


Frequently used volumes:
 
| Volume | Optional | Description |
| ---- | --- | --- |
| `SOLARANZEIGE_STORAGE` | no | The directory to persist /solaranzeige with Crontab Settings to |
| `INFLUXDB_STORAGE` | no | The directory to to persist /var/lib/influxdb to |
| `GRAFANA_STORAGE` | no | The directory to to persist /var/lib/grafana to |
| `PVFORECAST_STORAGE` | no | The directory to to persist /pvforecast to |
| `WWW_STORAGE` | no | The directory to to persist /var/www to |

When passing volumes please replace the name including the surrounding curly brackets with existing absolute paths with correct permissions.


> **Note:** `INFLUXDB_STORAGE` persist the Database for Grafana. 
> **Note:** `GRAFANA_STORAGE` persist Grafana settings and Dashboards. 
> **Note:** `WWW_STORAGE` persist logfiles and all script-files from solaranzeige.de. 
> **Note:** `PVFORECAST_STORAGE` persist PVForecast data from https://github.com/StefaE/PVForecast
>

