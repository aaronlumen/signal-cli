This is a webservice that will allow you to use signal as a bulletin  board. 
The idea is that the webservice will check for messages every 5 minutes and capture any messages sent to the registered number into a log file, parsing the messages into a user intuitive format instead of the verbosity that comes in a raw signal message.
This includes a basic php webpage that works with the service. 

You need to do the following:
you need to create the website and directory (Document Root, server name whatever you want to host the website)
You need to put these files into the website, primarily creating the crontab entry and pointing to the signal-parsing-webmessaging.sh file which will parse the incoming messages into the file specified in index.php.
That's really about it. 

Follow the activity.

A message comes in, it's received through the crontab service.  The crontab service parses the messages into a nice format with timestamp, sender and body of message only. 
The crontab points to the signal-parsing-webmessaging.sh which reads the message, parses it into a neat format and appends it to the file located in /var/log/signal.log 
the index.php reads the messages directly from the /var/log/signal.log file upon rendering the webpage. 
That is all so far.

Thank you.  


Aaron Surina
