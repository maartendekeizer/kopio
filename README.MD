
## Backup profile file

    name: Name of the profile
    source:
        # add one of the possible sources
        mysql:
            host:
            port:
            username:
            password:
            database:
            executable:
        postgresql:
            host:
            port:
            username:
            password:
            database:
            executable:
        filesystem:
            locations:
              - name:
                path:
        sftp:
            host:
            port:
            path:
            username:
            password:
            pathPrivateKey:
            passphrasePrivateKey:
            useAgent:
            fingerprint:
        mongodb:
            uri:
            executable:
    tmp:
        path: /tmp/backup
        mode: 0600
    target:
        # add one of the possible targets
        filesystem:
            path:
        sftp:
            host:
            port:
            path:
            username:
            password:
            pathPrivateKey:
            passphrasePrivateKey:
            useAgent:
            fingerprint:
        s3:
            region:
            version:
            endpoint:
            accessKey:
            secret:
            bucket:
            path:
        azureBlob:
            dsn:
            container:
            path:
    retention:
        simple:
            count: 10
    notifications:
        -   on: success
            email:
                from:
                to:
                subject:
    log: /var/log/kopio/profileName.log

## ToDo

- Add mongodb source
