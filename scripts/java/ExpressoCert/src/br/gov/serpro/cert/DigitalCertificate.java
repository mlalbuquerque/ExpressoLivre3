package br.gov.serpro.cert;

import br.gov.serpro.setup.Setup;
import java.awt.Frame;
import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.MalformedURLException;
import java.net.URL;
import java.security.AuthProvider;
import java.security.GeneralSecurityException;
import java.security.Key;
import java.security.KeyPair;
import java.security.KeyStore;
import java.security.KeyStoreException;
import java.security.PrivateKey;
import java.security.Provider;
import java.security.ProviderException;
import java.security.Security;
import java.security.cert.CertStore;
import java.security.cert.Certificate;
import java.security.cert.CollectionCertStoreParameters;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import java.util.Enumeration;
import java.util.List;
import java.util.Map;
import java.util.Properties;

import java.util.logging.Level;
import java.util.logging.Logger;
import javax.crypto.Cipher;
import javax.mail.Message;
import javax.mail.MessagingException;
import javax.mail.Session;
import javax.mail.internet.MimeBodyPart;
import javax.mail.internet.MimeMessage;
import javax.mail.internet.MimeMultipart;
import javax.net.ssl.SSLHandshakeException;
import javax.security.auth.login.LoginException;

import org.bouncycastle.asn1.ASN1EncodableVector;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.smime.SMIMECapability;
import org.bouncycastle.asn1.smime.SMIMECapabilityVector;
import org.bouncycastle.mail.smime.SMIMEException;
import org.bouncycastle.mail.smime.SMIMESignedGenerator;

import br.gov.serpro.ui.DialogBuilder;
import br.gov.serpro.util.Base64Utils;
import java.io.BufferedInputStream;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.StringReader;
import java.security.AlgorithmParameters;
import java.security.cert.CertificateEncodingException;
import java.text.DateFormat;
import java.util.HashMap;
import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import javax.activation.CommandMap;
import javax.activation.MailcapCommandMap;
import javax.mail.internet.ContentType;
import javax.mail.internet.HeaderTokenizer;
import javax.mail.internet.MimeUtility;
import javax.mail.internet.PreencodedMimeBodyPart;
import org.bouncycastle.cms.CMSException;
import org.bouncycastle.cms.RecipientId;
import org.bouncycastle.cms.RecipientInformation;
import org.bouncycastle.cms.RecipientInformationStore;
import org.bouncycastle.mail.smime.SMIMEEnvelopedParser;
import org.bouncycastle.mail.smime.SMIMEUtil;

/**
 * Classe que realiza todo o trabalho realizado com o certificado
 * @author M�rio C�sar Kolling - mario.kolling@serpro.gov.br
 */
//TODO: Criar exce��es para serem lan�adas, entre elas DigitalCertificateNotLoaded
//TODO: Adicionar setup
public class DigitalCertificate {

    private TokenCollection tokens;
    private String selectedCertificateAlias;
    private Certificate cert; // Certificado extra�do da KeyStore. Pode ser nulo.
    private KeyStore keyStore; // KeyStore que guarda o certificado do usu�rio. Pode ser nulo.
    private Frame parentFrame;
    private Setup setup;
    // TODO: Transformar pkcs12Input em uma string ou URL com o caminho para a KeyStore pkcs12
    private FileInputStream pkcs12Input; // stream da KeyStore pkcs12. Pode ser nulo.
    private String providerName; // Nome do SecurityProvider pkcs11 carregado. Pode ser nulo.
    private URL pageAddress; // Endere�o do host, onde a p�gina principal do
    private static final String HOME_SUBDIR; // Subdiret�rio dentro do diret�rio home do usu�rio. Dependente de SO.
    private static final String EPASS_2000; // Caminho da biblioteca do token ePass2000. Dependente de SO.
    private static final String CRLF = "\r\n"; // Separa campos na resposta do servi�o de verifica��o de certificados
    private static final String SUBJECT_ALTERNATIVE_NAME = "2.5.29.17"; // N�o � mais utilizado.
    private static final URL[] TRUST_STORES_URLS = new URL[3]; // URLs (file:/) das TrustStores, cacerts (jre),
    // trusted.certs e trusted.jssecerts (home do usu�rio)
    // Utilizadas para valida��o do certificado do servidor.
    private static final String[] TRUST_STORES_PASSWORDS = null; // Senhas para cada uma das TrustStores,
    // caso seja necess�rio.
    private int keystoreStatus;
    private static boolean useMSCapi = false;
    public static final int KEYSTORE_DETECTED = 0;
    public static final int KEYSTORE_NOT_DETECTED = 1;
    public static final int KEYSTORE_ALREADY_LOADED = 2;

    /*
     * Bloco est�tico que define os caminhos padr�es da instala��o da jre,
     * do diret�rio home do usu�rio, e da biblioteca de sistema do token ePass2000,
     * de acordo com o sistema operacional.
     */
    static {

	Properties systemProperties = System.getProperties();
	Map<String, String> env = System.getenv();

	/* TODO: Testar a exist�ncia de v�rios drivers de dispositivos. Determinar qual deve ser utilizado
	 * e guardar em uma property no subdiret�rio home do usu�rio.
	 */

	if (systemProperties.getProperty("os.name").equalsIgnoreCase("linux")) {
	    HOME_SUBDIR = "/.java/deployment/security";
	    EPASS_2000 = "/usr/lib/libepsng_p11.so";
	} else {
	    HOME_SUBDIR = "\\dados de aplicativos\\sun\\java\\deployment\\security";
	    EPASS_2000 = System.getenv("SystemRoot") + "\\system32\\ngp11v211.dll";
            DigitalCertificate.useMSCapi = true;
	}

	try {
	    if (systemProperties.getProperty("os.name").equalsIgnoreCase("linux")) {
		TRUST_STORES_URLS[0] = new File(systemProperties.getProperty("java.home") + "/lib/security/cacerts").toURI().toURL();
		TRUST_STORES_URLS[1] = new File(systemProperties.getProperty("user.home") + HOME_SUBDIR + "/trusted.certs").toURI().toURL();
		TRUST_STORES_URLS[2] = new File(systemProperties.getProperty("user.home") + HOME_SUBDIR + "/trusted.jssecerts").toURI().toURL();
	    } else {

		TRUST_STORES_URLS[0] = new File(systemProperties.getProperty("java.home") +
			"\\lib\\security\\cacerts").toURI().toURL();
		TRUST_STORES_URLS[1] = new File(systemProperties.getProperty("user.home") +
			HOME_SUBDIR + "\\trusted.certs").toURI().toURL();
		TRUST_STORES_URLS[2] = new File(systemProperties.getProperty("user.home") +
			HOME_SUBDIR + "\\trusted.jssecerts").toURI().toURL();
	    }

	    // Define os tipos smime no mailcap
	    MailcapCommandMap mailcap = (MailcapCommandMap) CommandMap.getDefaultCommandMap();

	    mailcap.addMailcap("application/pkcs7-signature;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.pkcs7_signature");
	    mailcap.addMailcap("application/pkcs7-mime;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.pkcs7_mime");
	    mailcap.addMailcap("application/x-pkcs7-signature;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.x_pkcs7_signature");
	    mailcap.addMailcap("application/x-pkcs7-mime;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.x_pkcs7_mime");
	    mailcap.addMailcap("multipart/signed;; x-java-content-handler=org.bouncycastle.mail.smime.handlers.multipart_signed");

	    CommandMap.setDefaultCommandMap(mailcap);



	} catch (MalformedURLException e) {
	    e.printStackTrace();
	}
    }

    /**
     *
     */
    public DigitalCertificate() {
	this.pageAddress = null;
	this.parentFrame = null;
    }

    /**
     * Construtor da classe. Recebe a {@link URL} da p�gina em que a Applet est� inclu�da.
     * @param pageAddress URL da p�gina em que a Applet est� inclu�da
     */
    private DigitalCertificate(URL pageAddress) {
	this.pageAddress = pageAddress;
	this.parentFrame = null;
    }

    private DigitalCertificate(Frame parent) {
	this.pageAddress = null;
	this.parentFrame = parent;
    }

    public DigitalCertificate(Frame parent, Setup setup) {
	this(parent);
	this.setup = setup;
    }

    public DigitalCertificate(URL pageAddress, Setup setup) {
	this(pageAddress);
	this.setup = setup;
    }

    public static boolean isUseMSCapi() {
        return useMSCapi;
    }

    public KeyStore getKeyStore() {
	return keyStore;
    }

    public int getKeystoreStatus() {
	return keystoreStatus;
    }

    public String getProviderName() {
	return providerName;
    }

    /**
     * Destr�i a Applet, removendo o security provider inicializado se o atributo providerName
     * for diferente de nulo.
     */
    public void destroy() {

        AuthProvider ap = null;

        if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
            System.out.println("logout no provider");
        }
        
        if (this.keyStore != null && this.keyStore.getProvider() instanceof AuthProvider) {
            ap = (AuthProvider) this.keyStore.getProvider();
        
            try {
                ap.logout();
            } catch (LoginException e) {
                if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                    e.printStackTrace();
                }
            }

            if (this.providerName != null) {
                Security.removeProvider(providerName);
            }
        }
        
        this.cert = null;
        this.selectedCertificateAlias = null;
        this.keyStore = null;
        this.pkcs12Input = null;
        this.providerName = null;
    }

    /**
     * Procura pelo token nos locais padr�es (Por enquanto s� suporta o token ePass200),
     * sen�o procura por um certificado A1 em System.getProperties().getProperty("user.home") +
     * HOME_SUBDIR  + "/trusted.clientcerts" e retorna um inteiro de acordo com resultado desta procura.
     *
     * @author	M�rio C�sar Kolling
     * @return	Retorna um destes valores inteiros DigitalCertificate.KEYSTORE_DETECTED,
     * 		DigitalCertificate.KEYSTORE_ALREADY_LOADED ou DigitalCertificate.KEYSTORE_NOT_DETECTED
     * @see	DigitalCertificate
     */
    public int init() {

        if (!DigitalCertificate.useMSCapi){
            this.tokens = new TokenCollection(setup);
        }

        Provider[] providers = Security.getProviders();
        if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
            for (Provider provider : providers) {
                System.out.println(provider.getInfo());
            }
        }

        int interfaceType = DigitalCertificate.KEYSTORE_NOT_DETECTED;

	try {
	    // Tenta abrir o Token padr�o (ePass2000).
	    loadKeyStore();

	} catch (Exception e1) {

	    if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		// N�o conseguiu abrir o token (ePass2000).
		System.out.println("Erro ao ler o token: " + e1.getMessage());
	    }

	    try {
		// Tenta abrir a keyStore padr�o
		// USER_HOME/deployment/security/trusted.clientcerts

		Properties props = System.getProperties();
		pkcs12Input = new FileInputStream(props.getProperty("user.home") + HOME_SUBDIR + "/trusted.clientcerts");

		// Se chegar aqui significa que arquivo de KeyStore existe.
		keyStore = KeyStore.getInstance("JKS");

	    } catch (Exception ioe) {
		// N�o conseguiu abrir a KeyStore pkcs12
		if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		    System.out.println(ioe.getMessage());
		}
	    }
	}


	if (keyStore == null) {
	    // N�o conseguiu inicializar a KeyStore. Mostra tela de login com usu�rio e senha.
	    this.keystoreStatus = DigitalCertificate.KEYSTORE_NOT_DETECTED;
	    //} else if (keyStore.getType().equalsIgnoreCase("pkcs11")){
	} else {
	    // Usa certificado digital.
	    try {
		// Testa se uma keystore j� foi carregada previamente
		if (keyStore.getType().equalsIgnoreCase("pkcs11") 
                        || keyStore.getType().equalsIgnoreCase("windows-my")) {
		    keyStore.load(null, null);
		} else {
		    keyStore.load(pkcs12Input, null);
		}

		// Se chegou aqui KeyStore est� liberada, mostrar tela de login sem pedir o pin.
		this.keystoreStatus = DigitalCertificate.useMSCapi ? DigitalCertificate.KEYSTORE_DETECTED : DigitalCertificate.KEYSTORE_ALREADY_LOADED ;

	    } catch (ProviderException e) {
		// Algum erro ocorreu, mostra  tela de login com usu�rio e senha.
		this.keystoreStatus = DigitalCertificate.KEYSTORE_NOT_DETECTED;
		if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		    e.printStackTrace();
		}
	    } catch (IOException e) {
		// KeyStore n�o est� liberada, mostra tela de login com o pin.
		if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		    System.out.println(e.getMessage());
		}
		this.keystoreStatus = DigitalCertificate.KEYSTORE_DETECTED;
	    } catch (GeneralSecurityException e) {
		if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		    e.printStackTrace();
		}
	    }
	}

	return keystoreStatus;

    }

    static public String verifyP7S(MimeMessage body){
        try {

            MimeMultipart mm = (MimeMultipart) body.getContent();
            externalLoop:for (int i = mm.getCount() - 1; i >= 0 ; i--){
                MimeBodyPart mbp = (MimeBodyPart) mm.getBodyPart(i);
                if (mbp.getContentType().contains("application/pkcs7-signature")){
                    String[] contentTypeArray = body.getHeader("Content-Type");
                    HeaderTokenizer hTokenizer = new HeaderTokenizer(contentTypeArray[0]);

                    String boundary = "";
                    HeaderTokenizer.Token t = hTokenizer.next();
                    for (; t.getType() != HeaderTokenizer.Token.EOF; t = hTokenizer.next()){
                        if (t.getType() == HeaderTokenizer.Token.ATOM && t.getValue().equalsIgnoreCase("boundary=")){
                            break;
                        }
                    }
                    t = hTokenizer.next();
                    if (t.getType() == HeaderTokenizer.Token.QUOTEDSTRING){
                        boundary = t.getValue();
                    }

                    InputStreamReader rawStreamReader = new InputStreamReader(mbp.getRawInputStream(), "iso-8859-1");

                    StringBuilder signature = new StringBuilder(256);
                    while (rawStreamReader.ready()){
                        char[] buffer = new char[256];
                        rawStreamReader.read(buffer);
                        signature.append(buffer);
                    }
                    
                    String[] array = signature.toString().split("\\r\\n|(?<!\\r)\\n");

                    boolean badFormat = false;
                    for (int j = 0; j < array.length; j++){
                        if (array[j].length() > array[0].length()){
                            badFormat = true;
                            break;
                        }
                    }

                    signature = null;
                    array = null;

                    if (badFormat){
                        BufferedInputStream parsedIS = new BufferedInputStream(mbp.getInputStream());
                        ByteArrayOutputStream baos = new ByteArrayOutputStream(parsedIS.available());
                        
                        while (parsedIS.available() > 0){
                            byte[] buffer = new byte[parsedIS.available()];
                            parsedIS.read(buffer);
                            baos.write(buffer);
                        }

                        Enumeration headers = headers = mbp.getAllHeaderLines();
                        String headersString = "";
                        while (headers.hasMoreElements()){
                            String header = (String) headers.nextElement();
                            headersString += header+"\r\n";
                        }

                        String base64Encoded = Base64Utils.der2pem(baos.toByteArray(), false);

                        mm.removeBodyPart(i);
                        body.saveChanges();
        
                        ByteArrayOutputStream oStream = new ByteArrayOutputStream();

                        oStream = new ByteArrayOutputStream();
                        body.writeTo(oStream);

                        BufferedReader reader = new BufferedReader(new StringReader(oStream.toString()));
                        OutputStream os = new ByteArrayOutputStream();

                        String line = "";
                        while ((line = reader.readLine()) != null ){
                            if (!line.equals("--"+boundary+"--")){
                                os.write((line+"\r\n").getBytes("iso-8859-1"));
                            }
                        }

                        return os.toString()
                                +"--"+boundary+"\r\n"+headersString
                                +"\r\n"+base64Encoded
                                +"--"+boundary+"--\r\n";

                    }

                    break externalLoop;
                }
            }

        } catch (IOException ex) {
            Logger.getLogger(DigitalCertificate.class.getName()).log(Level.SEVERE, null, ex);
        } catch (MessagingException ex) {
            Logger.getLogger(DigitalCertificate.class.getName()).log(Level.SEVERE, null, ex);
        } catch (ClassCastException ex) {
            Logger.getLogger(DigitalCertificate.class.getName()).log(Level.SEVERE, null, ex);
        }

        return null;
    }

    /**
     * Usado para assinar digitalmente um e-mail.
     * @param mime
     * @return String vazia
     */
    public String signMail(Map<String, String> data) throws IOException, GeneralSecurityException, SMIMEException, MessagingException {

        Key privateKey = null;
	if (this.keystoreStatus == DigitalCertificate.KEYSTORE_DETECTED) {
            char[] pin = null;
            if (!DigitalCertificate.useMSCapi) {
                String sPin = DialogBuilder.showPinDialog(this.parentFrame, this.setup);
                if (sPin != null) {
                    pin = sPin.toCharArray();
                }
                else {
                    return null;
                }
            }

            try {
                openKeyStore(pin);
            }
            catch (Exception e)
            {
                if (e instanceof IOException){
                    throw new IOException(e);
                }
                else if (e instanceof GeneralSecurityException){
                    throw new GeneralSecurityException(e);
                }
            }
            
            if (this.selectedCertificateAlias == null){
                return null;
            }
            privateKey = this.keyStore.getKey(this.selectedCertificateAlias, pin);
	    
	} /*
	else if (this.keystoreStatus == DigitalCertificate.KEYSTORE_ALREADY_LOADED){
	if (DialogBuilder.showPinNotNeededDialog(this.parentFrame)){
	openKeyStore(null);
	privateKey = this.keyStore.getKey(keyStore.aliases().nextElement(), null);
	}
	else {
	return null;
	}
	}
	 */ else {

	    //DialogBuilder.showMessageDialog(this.parentFrame, "Nenhum token/smartcard foi detectado.\nOpera��o n�o p�de ser realizada!");
	    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMessages", "DigitalCertificate001"), this.setup);
	    return null;
	}
        
	Security.addProvider(new org.bouncycastle.jce.provider.BouncyCastleProvider());

	Certificate certificate = getCert();

	KeyPair keypair = new KeyPair(certificate.getPublicKey(), (PrivateKey) privateKey);

	// Cria a cadeia de certificados que a gente vai enviar
	List certList = new ArrayList();

	certList.add(certificate);

	//
	// create the base for our message
	//
	String fullMsg = data.get("body");

	if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Corpo do e-mail:\n" + fullMsg + "\n");
	}

	//
	// Get a Session object and create the mail message
	//
	Properties props = System.getProperties();
	Session session = Session.getDefaultInstance(props, null);

	InputStream is = new ByteArrayInputStream(fullMsg.getBytes("iso-8859-1"));
	MimeMessage unsignedMessage = new MimeMessage(session, is);

	//
	// create a CertStore containing the certificates we want carried
	// in the signature
	//
	if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Provider: " + providerName);
	}
	CertStore certsAndcrls = CertStore.getInstance(
		"Collection",
		new CollectionCertStoreParameters(certList), "BC");

	//
	// create some smime capabilities in case someone wants to respond
	//
	ASN1EncodableVector signedAttrs = new ASN1EncodableVector();

	SMIMECapabilityVector caps = new SMIMECapabilityVector();

	caps.addCapability(SMIMECapability.dES_EDE3_CBC);
	caps.addCapability(SMIMECapability.rC2_CBC, 128);
	caps.addCapability(SMIMECapability.dES_CBC);

	SMIMESignedGenerator gen = new SMIMESignedGenerator(unsignedMessage.getEncoding());

	//SMIMESignedGenerator gen = new SMIMESignedGenerator();

        // TODO:  Verificar se este c�digo � suficiente para cumprir a norma.
        if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
            Provider.Service sha512 = this.keyStore.getProvider().getService("MessageDigest", "SHA-512");
            Provider.Service sha256 = this.keyStore.getProvider().getService("MessageDigest", "SHA-256");

            if (sha512 != null){
                System.out.println("sha512: " + sha512.getType() + " : " + sha512.getAlgorithm());
            } else {
                    System.out.println("sha512: n�o suportado!");
            }

            if (sha256 != null){
                    System.out.println("sha256: " + sha256.getType() + " : " + sha256.getAlgorithm());
            } else {
                    System.out.println("sha256: n�o suportado!");
            }
        }

        // TODO: Verificar problema com MessageDigest SHA-512.
//        if (this.keyStore.getProvider().getService("MessageDigest", "SHA-512") != null){
//            gen.addSigner(keypair.getPrivate(), (X509Certificate) certificate, SMIMESignedGenerator.DIGEST_SHA512, new AttributeTable(signedAttrs), null);
//        }
//        else
        if (this.keyStore.getProvider().getService("MessageDigest", "SHA-256") != null){
            gen.addSigner(keypair.getPrivate(), (X509Certificate) certificate, SMIMESignedGenerator.DIGEST_SHA256, new AttributeTable(signedAttrs), null);
        }
        else {
            gen.addSigner(keypair.getPrivate(), (X509Certificate) certificate, SMIMESignedGenerator.DIGEST_SHA1, new AttributeTable(signedAttrs), null);
        }

	gen.addCertificatesAndCRLs(certsAndcrls);

	//TODO: Extrair todos os headers de unsignedMessage

	// Gera a assinatura
	Object content = unsignedMessage.getContent();

	//TODO: igualar unsignedMessage a null
	//TODO: Pegar os headers do objeto que guardar� esses headers quando necess�rio.

	MimeMultipart mimeMultipartContent = null;
	PreencodedMimeBodyPart mimeBodyPartContent = null;

	if (content.getClass().getName().contains("MimeMultipart")) {
	    mimeMultipartContent = (MimeMultipart) content;
	} else {
	    String encoding = MimeUtility.getEncoding(unsignedMessage.getDataHandler());
	    mimeBodyPartContent = new PreencodedMimeBodyPart(encoding);
	    if (encoding.equalsIgnoreCase("quoted-printable")) {
		ByteArrayOutputStream os = new ByteArrayOutputStream();
		OutputStream encode = MimeUtility.encode(os, encoding);
		OutputStreamWriter writer = new OutputStreamWriter(encode, "iso-8859-1");
		writer.write(content.toString());
		writer.flush();
		mimeBodyPartContent.setText(os.toString(), "iso-8859-1");
		os = null;
		encode = null;
		writer = null;
	    } else {
		mimeBodyPartContent.setText(content.toString(), "iso-8859-1");
	    }
	    mimeBodyPartContent.setHeader("Content-Type", unsignedMessage.getHeader("Content-Type", null));
	}
	content = null;

	//
	// extract the multipart object from the SMIMESigned object.
	//
	MimeMultipart mm = null;
	if (mimeMultipartContent == null) {
	    mm = gen.generate(mimeBodyPartContent, providerName);
	    mimeBodyPartContent = null;
	} else {
	    MimeBodyPart multipartMsg = new MimeBodyPart();
	    multipartMsg.setContent(mimeMultipartContent);
	    mm = gen.generate(multipartMsg, providerName);
	    multipartMsg = null;
	    mimeMultipartContent = null;
	}

	gen = null;

	MimeMessage body = new MimeMessage(session);
	body.setFrom(unsignedMessage.getFrom()[0]);
	body.setRecipients(Message.RecipientType.TO, unsignedMessage.getRecipients(Message.RecipientType.TO));
	body.setRecipients(Message.RecipientType.CC, unsignedMessage.getRecipients(Message.RecipientType.CC));
	body.setRecipients(Message.RecipientType.BCC, unsignedMessage.getRecipients(Message.RecipientType.BCC));
	body.setSubject(unsignedMessage.getSubject(), "iso-8859-1");

	// Atrafuia o resto dos headers
	body.setHeader("Return-Path", unsignedMessage.getHeader("Return-Path", null));
	body.setHeader("Message-ID", unsignedMessage.getHeader("Message-ID", null));
	body.setHeader("X-Priority", unsignedMessage.getHeader("X-Priority", null));
	body.setHeader("X-Mailer", unsignedMessage.getHeader("X-Mailer", null));
        body.setHeader("Importance", unsignedMessage.getHeader("Importance", null));
	body.setHeader("Disposition-Notification-To", unsignedMessage.getHeader("Disposition-Notification-To", null));
	body.setHeader("Date", unsignedMessage.getHeader("Date", null));
	body.setContent(mm, mm.getContentType());
	mm = null;

	if (setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("\nHeaders do e-mail original:\n");
	}

	body.saveChanges();

        ByteArrayOutputStream oStream = new ByteArrayOutputStream();

	oStream = new ByteArrayOutputStream();
        body.writeTo(oStream);

        String verified = DigitalCertificate.verifyP7S(body);
        body = null;

        if (verified != null){
            return verified;
        } else {
            return oStream.toString("iso-8859-1");
        }
    }

    /**
     * M�todo utilizado para criptografar um e-mail
     * @param source
     * @return
     */
    public String cipherMail(Map<String, String> data) throws IOException, GeneralSecurityException, MessagingException, CMSException, SMIMEException {

	//Pega certificado do usu�rio.

	Key privateKey = null;
	if (this.keystoreStatus == DigitalCertificate.KEYSTORE_DETECTED) {
            char[] pin = null;
	    if (!DigitalCertificate.useMSCapi) {
               String sPin = DialogBuilder.showPinDialog(this.parentFrame, this.setup);
               if (sPin != null) {
                   pin = sPin.toCharArray();
               }
               else {
                   return null;
               }
            }

            openKeyStore(pin);
            if (this.selectedCertificateAlias == null){
                return null;
            }
            privateKey = this.keyStore.getKey(this.selectedCertificateAlias, pin);
	    
	} /*
	else if (this.keystoreStatus == DigitalCertificate.KEYSTORE_ALREADY_LOADED){
	if (DialogBuilder.showPinNotNeededDialog(this.parentFrame)){
	openKeyStore(null);
	privateKey = this.keyStore.getKey(keyStore.aliases().nextElement(), null);
	}
	else {
	return null;
	}
	}
	 */ else {

	    //DialogBuilder.showMessageDialog(this.parentFrame, "Nenhum token/smartcard foi detectado.\nOpera��o n�o p�de ser realizada!");
	    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMessages", "DigitalCertificate001"), this.setup);
	    return null;
	}

	Security.addProvider(new org.bouncycastle.jce.provider.BouncyCastleProvider());

	X509Certificate cert = (X509Certificate) getCert();

	RecipientId recId = new RecipientId();
	recId.setSerialNumber(cert.getSerialNumber());
	recId.setIssuer(cert.getIssuerX500Principal());

	Properties props = System.getProperties();
	Session session = Session.getDefaultInstance(props, null);

	String fullMsg = data.get("body");
	InputStream is = new ByteArrayInputStream(fullMsg.getBytes("iso-8859-1"));
	MimeMessage encriptedMsg = new MimeMessage(session, is);

	Provider prov = Security.getProvider(providerName);
	if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Servi�os do provider " + providerName + ":\n" + prov.getInfo());
	    for (Provider.Service service : prov.getServices()) {
		System.out.println(service.toString() + ": " + service.getAlgorithm());
	    }
	}

	if (setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Email criptografado:\n" + fullMsg);
	}

	SMIMEEnvelopedParser m = new SMIMEEnvelopedParser(encriptedMsg);
	if (setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Algoritmo de encripta��o: " + m.getEncryptionAlgOID());
	}

	AlgorithmParameters algParams = m.getEncryptionAlgorithmParameters("BC");
	if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
	    System.out.println("Par�metros do algoritmo: " + algParams.toString());
	}

	RecipientInformationStore recipients = m.getRecipientInfos();
	RecipientInformation recipient = recipients.get(recId);

	if (recipient != null) {
	    String retorno;

	    MimeBodyPart decriptedBodyPart = SMIMEUtil.toMimeBodyPart(recipient.getContent(privateKey, getProviderName()));

	    if ((new ContentType(decriptedBodyPart.getContentType())).getSubType().equalsIgnoreCase("x-pkcs7-mime")) {
		StringBuffer sb = new StringBuffer(encriptedMsg.getSize());

		for (Enumeration e = encriptedMsg.getAllHeaderLines(); e.hasMoreElements();) {
		    String header = (String) e.nextElement();
		    if (!header.contains("Content-Type") &&
			    !header.contains("Content-Transfer-Encoding") &&
			    !header.contains("Content-Disposition")) {
			sb.append(header);
			sb.append("\r\n");
		    }
		}
		ByteArrayOutputStream oStream = new ByteArrayOutputStream();
		decriptedBodyPart.writeTo(oStream);

                decriptedBodyPart = null;
		encriptedMsg = null;

		sb.append(oStream.toString("iso-8859-1"));

		retorno = sb.toString();

	    } else {
                
		encriptedMsg.setContent(decriptedBodyPart.getContent(), decriptedBodyPart.getContentType());
		encriptedMsg.saveChanges();

		ByteArrayOutputStream oStream = new ByteArrayOutputStream();
		encriptedMsg.writeTo(oStream);
		encriptedMsg = null;

		retorno = oStream.toString("iso-8859-1");
	    }

            // Corrige problemas com e-mails vindos do Outlook
            // Corrige linhas que s�o terminadas por \n (\x0A) e deveriam ser terminadas por \r\n (\x0D\x0A)
            Pattern p = Pattern.compile("(?<!\\r)\\n");
            Matcher matcher = p.matcher(retorno);
            retorno = matcher.replaceAll(CRLF);

	    return retorno;
	} else {
	    //DialogBuilder.showMessageDialog(this.parentFrame, "N�o � poss�vel ler este e-mail com o Certificado Digital apresentado!\n" +
	    //        "Motivo: Este e-mail n�o foi cifrado com a chave p�blica deste Certificado Digital.");
	    DialogBuilder.showMessageDialog(this.parentFrame, setup.getLang("ExpressoCertMessages", "DigitalCertificate002"), this.setup);
	    return null;
	}
    }

    /**
     * Carrega um novo SecurityProvider
     * @param pkcs11Config Linha de configura��o do SmartCard ou Token
     * @throws KeyStoreException Quando n�o conseguir iniciar a KeyStore, ou a lib do Token
     * 							 ou Smartcard n�o foi encontrada, ou o usu�rio n�o inseriu o Token.
     */
    private void loadKeyStore() throws GeneralSecurityException {

        try{
            if (!DigitalCertificate.useMSCapi) {
                if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                    System.out.println("Carregando provider: PKCS11");
                }
                this.keyStore = KeyStore.getInstance("PKCS11");
                this.providerName = keyStore.getProvider().getName();
            }
            else {
                if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                    System.out.println("Carregando provider: SunMSCAPI");
                }
                this.keyStore = KeyStore.getInstance("Windows-MY", "SunMSCAPI");
                this.providerName = this.keyStore.getProvider().getName();

                if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                    System.out.println(keyStore.getProvider().getName() +" carregado!");
                }
            }
        }
        catch (GeneralSecurityException kex){
            if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                System.out.println("Erro ao carregar provider: PKCS11");
                Throwable cause = kex.getCause();
                kex.printStackTrace();
                if (cause != null){
                    cause.printStackTrace();
                }
            }
            throw kex;
        }
    }

    Map<String, String> getAliasesList() throws IOException, KeyStoreException{

        if (setup.getParameter("debug").equalsIgnoreCase("true")) {
            System.out.println("Getting Aliases");
        }

        Map<String, String> aliases = new HashMap<String, String>();
      
        for (Enumeration<String> al = this.keyStore.aliases(); al.hasMoreElements();){
            String alias = al.nextElement();
            X509Certificate certObj = (X509Certificate) this.keyStore.getCertificate(alias);
            
            StringBuilder selector = new StringBuilder();
            // get more info to generate the value
            // Subject's CN / Issuer's CN / Expiration Data
            String subject = certObj.getSubjectX500Principal().getName();
            int pInicial = subject.indexOf('=')+1;
            int pFinal = subject.indexOf(',', pInicial);
            selector.append(subject.substring(pInicial, pFinal)+" | ");

            String issuer = certObj.getIssuerX500Principal().getName();
            pInicial = issuer.indexOf('=')+1;
            pFinal = issuer.indexOf(',', pInicial);
            selector.append(issuer.substring(pInicial, pFinal)+" | ");

            // TODO: get the system locale
            Locale locale = new Locale("pt", "BR");
            DateFormat df = DateFormat.getDateInstance(DateFormat.MEDIUM, locale);
            selector.append(df.format(certObj.getNotAfter())+" | ");

            selector.append("("+certObj.getSerialNumber()+")");

            aliases.put(alias, selector.toString());
            
        }

        return aliases;
    }

//    public void removeCertificate() throws IOException {
//        Token token = tokens.getRegisteredTokens().iterator().next();
//        token.removeCertificate();
//    }
//
//    public void writeCerts(char[] pin, LinkedHashMap<char[], Certificate> certs) throws IOException{
//        Token token = tokens.getRegisteredTokens().iterator().next();
//        token.getAliases();
//
//        for (Map.Entry<char[], Certificate> entry : certs.entrySet()) {
//            token.writeCert(pin, entry.getKey(), entry.getValue());
//        }
//    }

    /**
     *  Abre a keystore passando o pin
     *  @param pin pin para acessar o Token
     */
    @SuppressWarnings("empty-statement")
    public void openKeyStore(char[] pin) throws IOException {
        // TODO:  Verify if object DigitalCertificate was initiated
	try {

	    if (this.keyStore.getType().equals("PKCS11")) {
		this.keyStore.load(null, pin);
	    } else if (this.keyStore.getType().equals("Windows-MY")) {
                this.keyStore.load(null, null);
            } else {
		this.keyStore.load(this.pkcs12Input, pin);
	    }

            // selecionador de certificado
            this.selectedCertificateAlias = DialogBuilder.showCertificateSelector(this.parentFrame,
                    this.setup, this.getAliasesList());
	    if (this.selectedCertificateAlias != null){
                this.cert = this.keyStore.getCertificate(this.selectedCertificateAlias);
            
                if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
                    System.out.println("Selected Alias: "+this.selectedCertificateAlias);
                    System.out.println("Aliases (" + this.keyStore.size() + "): ");
                    for (Enumeration alias = this.keyStore.aliases(); alias.hasMoreElements();) {
                        System.out.println(alias.nextElement());
                    }
                }
            }

	} catch (GeneralSecurityException e) {
	    if (this.setup.getParameter("debug").equalsIgnoreCase("true")) {
		e.printStackTrace();
	    }
	}

    }

    /**
     * @return the cert
     */
    Certificate getCert() {
	return this.cert;
    }

    /**
     * Get a PEM encoded instance of the user certificate
     * @return PEM encoded Certificate
     * @throws CertificateEncodingException
     */
    public String getPEMCertificate() throws CertificateEncodingException {
        if (this.cert != null){
            return Base64Utils.der2pem(this.cert.getEncoded(), true);
        }
        return null;

    }

    /**
     * @param cert the cert to set
     */
    void setCert(Certificate cert) {
	this.cert = cert;
    }
}
