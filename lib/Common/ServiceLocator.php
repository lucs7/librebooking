<?php

class ServiceLocator
{
    /**
     * @var Database
     */
    private static $_database;

    /**
     * @var Server
     */
    private static $_server;

    private static ?IRestServer $_apiServer = null;

    /**
     * @var IEmailService
     */
    private static $_emailService;

    /**
     * @var Booked\IFileSystem
     */
    private static $_fileSystem;

    /**
     * @return Database
     */
    public static function GetDatabase()
    {
        require_once ROOT_DIR.'lib/Database/namespace.php';

        if (null == self::$_database) {
            self::$_database = DatabaseFactory::GetDatabase();
        }

        return self::$_database;
    }

    public static function SetDatabase(Database $database)
    {
        self::$_database = $database;
    }

    /**
     * @return Server
     */
    public static function GetApiServer(): ?IRestServer
    {
        return self::$_apiServer;
    }

    public static function SetApiServer(IRestServer $apiServer)
    {
        self::$_apiServer = $apiServer;
    }

    /**
     * @return Server
     */
    public static function GetServer()
    {
        require_once ROOT_DIR.'lib/Server/namespace.php';

        if (null == self::$_server) {
            self::$_server = new Server();
        }

        return self::$_server;
    }

    public static function SetServer(Server $server)
    {
        self::$_server = $server;
    }

    /**
     * @static
     *
     * @return IEmailService
     */
    public static function GetEmailService()
    {
        require_once ROOT_DIR.'lib/Email/namespace.php';

        if (null == self::$_emailService) {
            if (Configuration::Instance()->GetKey(ConfigKeys::ENABLE_EMAIL, new BooleanConverter())) {
                self::$_emailService = new EmailService();
            //                self::$_emailService = new EmailLogger();
            } else {
                self::$_emailService = new NullEmailService();
            }
        }

        return self::$_emailService;
    }

    public static function SetEmailService(IEmailService $emailService)
    {
        self::$_emailService = $emailService;
    }

    /**
     * @static
     *
     * @return Booked\FileSystem
     */
    public static function GetFileSystem()
    {
        require_once ROOT_DIR.'lib/FileSystem/namespace.php';

        if (null == self::$_fileSystem) {
            self::$_fileSystem = new Booked\FileSystem();
        }

        return self::$_fileSystem;
    }

    public static function SetFileSystem(Booked\IFileSystem $fileSystem)
    {
        self::$_fileSystem = $fileSystem;
    }

    public static function GetUserSession(): ?UserSession
    {
        if (!is_null(self::$_server)) {
            $userSession = self::$_server->GetUserSession();
            if (!$userSession instanceof NullUserSession) {
                return $userSession;
            }
        }
        if (is_null(self::$_apiServer)) {
            return null;
        }

        return self::$_apiServer->GetSession();
    }
}
