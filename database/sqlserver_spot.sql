IF DB_ID(N'SPOT') IS NULL
BEGIN
    CREATE DATABASE SPOT;
END;
GO

USE SPOT;
GO

IF OBJECT_ID(N'dbo.sessions', N'U') IS NOT NULL DROP TABLE dbo.sessions;
IF OBJECT_ID(N'dbo.password_reset_tokens', N'U') IS NOT NULL DROP TABLE dbo.password_reset_tokens;
IF OBJECT_ID(N'dbo.tb_usuario', N'U') IS NOT NULL DROP TABLE dbo.tb_usuario;
IF OBJECT_ID(N'dbo.tb_clientetipo', N'U') IS NOT NULL DROP TABLE dbo.tb_clientetipo;
IF OBJECT_ID(N'dbo.tb_cliente', N'U') IS NOT NULL DROP TABLE dbo.tb_cliente;
IF OBJECT_ID(N'dbo.tb_empresa', N'U') IS NOT NULL DROP TABLE dbo.tb_empresa;
IF OBJECT_ID(N'dbo.tb_pessoa', N'U') IS NOT NULL DROP TABLE dbo.tb_pessoa;
IF OBJECT_ID(N'dbo.tb_perfil', N'U') IS NOT NULL DROP TABLE dbo.tb_perfil;
GO

CREATE TABLE dbo.tb_perfil (
    id_perfil BIGINT IDENTITY(1,1) NOT NULL,
    perfil NVARCHAR(40) NOT NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_perfil PRIMARY KEY (id_perfil),
    CONSTRAINT UQ_tb_perfil_perfil UNIQUE (perfil)
);

CREATE TABLE dbo.tb_pessoa (
    id_pessoa BIGINT IDENTITY(1,1) NOT NULL,
    CPF NVARCHAR(14) NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_pessoa PRIMARY KEY (id_pessoa)
);

CREATE UNIQUE INDEX UX_tb_pessoa_CPF
    ON dbo.tb_pessoa (CPF)
    WHERE CPF IS NOT NULL;

CREATE TABLE dbo.tb_empresa (
    id_empresa BIGINT IDENTITY(1,1) NOT NULL,
    CNPJ NVARCHAR(18) NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_empresa PRIMARY KEY (id_empresa)
);

CREATE UNIQUE INDEX UX_tb_empresa_CNPJ
    ON dbo.tb_empresa (CNPJ)
    WHERE CNPJ IS NOT NULL;

CREATE TABLE dbo.tb_cliente (
    id_cliente BIGINT IDENTITY(1,1) NOT NULL,
    NomeCliente NVARCHAR(120) NOT NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_cliente PRIMARY KEY (id_cliente)
);

CREATE TABLE dbo.tb_clientetipo (
    id_pessoa_empresa BIGINT NOT NULL,
    id_cliente BIGINT NOT NULL,
    tipo NVARCHAR(10) NOT NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_clientetipo PRIMARY KEY (id_pessoa_empresa, id_cliente, tipo),
    CONSTRAINT CK_tb_clientetipo_tipo CHECK (tipo IN (N'pessoa', N'empresa')),
    CONSTRAINT FK_tb_clientetipo_cliente FOREIGN KEY (id_cliente)
        REFERENCES dbo.tb_cliente (id_cliente)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE dbo.tb_usuario (
    id_usuario BIGINT IDENTITY(1,1) NOT NULL,
    Email NVARCHAR(120) NOT NULL,
    email_verified_at DATETIME2 NULL,
    Senha NVARCHAR(255) NOT NULL,
    id_perfil BIGINT NOT NULL,
    id_pessoa BIGINT NULL,
    remember_token NVARCHAR(100) NULL,
    created_at DATETIME2 NULL,
    updated_at DATETIME2 NULL,
    CONSTRAINT PK_tb_usuario PRIMARY KEY (id_usuario),
    CONSTRAINT UQ_tb_usuario_Email UNIQUE (Email),
    CONSTRAINT FK_tb_usuario_perfil FOREIGN KEY (id_perfil)
        REFERENCES dbo.tb_perfil (id_perfil)
        ON UPDATE CASCADE,
    CONSTRAINT FK_tb_usuario_pessoa FOREIGN KEY (id_pessoa)
        REFERENCES dbo.tb_pessoa (id_pessoa)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE TABLE dbo.password_reset_tokens (
    email NVARCHAR(255) NOT NULL,
    token NVARCHAR(255) NOT NULL,
    created_at DATETIME2 NULL,
    CONSTRAINT PK_password_reset_tokens PRIMARY KEY (email)
);

CREATE TABLE dbo.sessions (
    id NVARCHAR(255) NOT NULL,
    user_id BIGINT NULL,
    ip_address NVARCHAR(45) NULL,
    user_agent NVARCHAR(MAX) NULL,
    payload NVARCHAR(MAX) NOT NULL,
    last_activity INT NOT NULL,
    CONSTRAINT PK_sessions PRIMARY KEY (id),
    CONSTRAINT FK_sessions_usuario FOREIGN KEY (user_id)
        REFERENCES dbo.tb_usuario (id_usuario)
        ON DELETE SET NULL
);

CREATE INDEX IX_sessions_user_id ON dbo.sessions (user_id);
CREATE INDEX IX_sessions_last_activity ON dbo.sessions (last_activity);
GO

SET IDENTITY_INSERT dbo.tb_perfil ON;

INSERT INTO dbo.tb_perfil (id_perfil, perfil, created_at, updated_at)
VALUES
    (1, N'Administrador', SYSDATETIME(), SYSDATETIME()),
    (2, N'Gestor', SYSDATETIME(), SYSDATETIME()),
    (3, N'Analista', SYSDATETIME(), SYSDATETIME()),
    (4, N'Convidado', SYSDATETIME(), SYSDATETIME()),
    (5, N'AdmGest', SYSDATETIME(), SYSDATETIME());

SET IDENTITY_INSERT dbo.tb_perfil OFF;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.tb_usuario WHERE Email = N'admin@spot.local')
BEGIN
    INSERT INTO dbo.tb_usuario (
        Email,
        Senha,
        id_perfil,
        email_verified_at,
        created_at,
        updated_at
    )
    VALUES (
        N'admin@spot.local',
        N'$2y$12$0Nm6xeWOX6vWc7esvtZst.68XHXhELaKupTSfHhgibi0IxD5qYWoO',
        1,
        SYSDATETIME(),
        SYSDATETIME(),
        SYSDATETIME()
    );
END;
GO

USE SPOT;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.tb_usuario WHERE Email = N'admin@spot.local')
BEGIN
    INSERT INTO dbo.tb_usuario (
        Email,
        Senha,
        id_perfil,
        email_verified_at,
        created_at,
        updated_at
    )
    VALUES (
        N'admin@spot.local',
        N'$2y$12$0Nm6xeWOX6vWc7esvtZst.68XHXhELaKupTSfHhgibi0IxD5qYWoO',
        1,
        SYSDATETIME(),
        SYSDATETIME(),
        SYSDATETIME()
    );
END;
GO

SELECT id_usuario, Email, id_perfil FROM dbo.tb_usuario;
GO