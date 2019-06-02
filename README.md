# ftc
Sistema sat2app for FTC Clients

## Instalación base de datos firebird:

Installation: apt-get install firebird2.5-superclassic
Configuration: dpkg-reconfigure firebird2.5-superclassic
Install dev tools: apt-get install firebird2.5-dev
Install sample: firebird2.5-examples 
    Just install employee.dbf 
    Under: /usr/share/doc/firebird2.5-examples/
Data location: /var/lib/firebird/2.5/data/
Connection user: firebird.firebird
Sample database: connect "localhost:/var/lib/firebird/2.5/data/employee.fdb" user 'SYSDBA' password 'MASTERKEY';
