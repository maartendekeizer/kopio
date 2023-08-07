
YAML file:

	name: 'mdk_MariaDB'

	source:
		mariadb:
			 username: 
			 password:
			 host: 127.0.0.1
			 port: 3306
			 database: 
	#     postgres:
	#        username: 
	#        password: 
	#        password:         
	# Password: gevuld -> via command line (Unix) 
	# Niet gevuld -> via pgpass (windows/unix) anders prompt        
	# https://www.postgresql.org/docs/current/libpq-pgpass.html        
	#        host: 127.0.0.1
	#       port: 5432
	#       database: sass
			
	#    mongodb:
	#        url:
	#    filesystem:
	#        path:
	#    sftp:
	#        host:
	#        port: 22
	#        path:
	#        username:
	#        password:
	#        private_key:
	#        passphrase:
	#        fingerprint:
	#        temp:

	target:
		filesystem: c:\Users\Mariendekeizer\Kopio\var\storage\kopio\marien\backup

	   
	#    awss3:
	#        region:
	#        bucket:
	#        prefix:
	#        access_key:
	#        secret:
	#        version: latest
	#        endpoint:
	#        use_path_style_endpoint: true
			
	#    azureblob:
	#        dsn:
	#        container:
	#        prefix:
	#     sftp:
	#        host: 
	#        username: 
	#        password:
	#        path_private_key: 
	#        passphrase: 
	#        port: 22
	#        use_agent: false
	#        timeout: 10
	#        max_tries: 4
	#        fingerprint:
	#        connectivity_checker:
	#        path: 
		
	retention:
		simple:
			days: 2    
		
	notifications:
	# when: always = failure+success else only at failure
		when: always
		email: 
		subject: KOPIO
		
		
Command		
	symfony console app:create-backup [ dir-with-yaml-files] testAWS.yaml		// single file
	symfony console app:create-backup [ dir-with-yaml-files]  		            // all	
		
		

