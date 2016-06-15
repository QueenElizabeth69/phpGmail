# Simple Oauth2 & Gmail Sender for PHP

After switching to Oauth and ditching SMTP authentication, 
it became too complex to send mails via PHP using Gmail.
Because all the available solutions requires over complex code inclusion, I've written this 
piece of code for simple projects to exploit Gmail service to send mails from PHP scripts.
It is possible to add attachments and send multipart text only mails. 
It should also be possible to send HTML messages with a little modification to code. 

* Requires PHP imap extension to format mail content.
* Designed to be used mainly from console.
* Needs write access to the folder which script executed for the token persistency.  

Provides a simple object API which can be used like this.

```php
$mail = new Gmail();
$mail->oauth->scope         = 'https://mail.google.com/'; 
$mail->oauth->user          = 'yourname@gmail.com';
$mail->oauth->conffile      = "conf.json";  // JSON Conf File for Google Registered APP that allowed to exploit Gmail
$mail->oauth->httpc->ua     = "dtmail/1.5"; // Whatever the project name is registered from Google Cloud Console

$mail->formatter->From('name surname <name.surname@gmail.com>');
$mail->formatter->Subject('Subject Line');
$mail->formatter->Text("Textonly Content");
		
$mail->formatter->Recipient(Recipient Address <rec@mail.address>);
$mail->formatter->CC('CCd Recipient <ccdrec@mail.address>');
$mail->formatter->BCC('BCCd Recipient <bccdrec@mail.address>');

$mail->formatter->addAttachment("filepath");

$return = $mail->send();
```