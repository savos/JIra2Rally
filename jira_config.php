<?php

    namespace Jira;

    const DOMAIN     = 'https://YourJiraDomain.com/jira/rest/api';  // point to your Jira domain
    const VERSION    = '/latest';
    const USERNAME   = 'YourUsername';                              // Jira username
    const PASSWORD   = 'YourPassword';                              // Jira password

    const SEARCH     = '/search?jql=';
    const PROJECT    = 'project=YourProjectName';                   // set project you want to transfer
    const STARTAT    = '&startAt=';
    const MAXRESULTS = '&maxResults=';
    const UPDATED    = '&updated<';

