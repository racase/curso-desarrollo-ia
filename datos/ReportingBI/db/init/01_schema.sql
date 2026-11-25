-- Generado por tools/generate_seed.py. No editar a mano: cambia el generador y vuelve a ejecutarlo.

SET NOCOUNT ON;
GO

IF DB_ID(N'NAVDEMO') IS NOT NULL
BEGIN
    ALTER DATABASE [NAVDEMO] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE [NAVDEMO];
END
GO
CREATE DATABASE [NAVDEMO] COLLATE Modern_Spanish_CI_AS;
GO
USE [NAVDEMO];
GO

-- Tabla de empresas de NAV
CREATE TABLE [dbo].[Company] (
    [timestamp] rowversion NOT NULL,
    [Name] nvarchar(30) NOT NULL,
    [Display Name] nvarchar(250) NOT NULL,
    CONSTRAINT [Company$0] PRIMARY KEY CLUSTERED ([Name])
);
GO

-- Tablas de la empresa CONSTRUCTORA DEMO
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Customer] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Search Name] nvarchar(50) NOT NULL,
    [City] nvarchar(30) NOT NULL,
    [VAT Registration No_] nvarchar(20) NOT NULL,
    [Blocked] int NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Customer$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Resource] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Base Unit of Measure] nvarchar(10) NOT NULL,
    [Unit Cost] decimal(38,20) NOT NULL,
    [Blocked] tinyint NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Resource$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Dimension Value] (
    [timestamp] rowversion NOT NULL,
    [Dimension Code] nvarchar(20) NOT NULL,
    [Code] nvarchar(20) NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Dimension Value Type] int NOT NULL,
    [Blocked] tinyint NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Dimension Value$0] PRIMARY KEY CLUSTERED ([Dimension Code], [Code])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Search Description] nvarchar(50) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Description 2] nvarchar(50) NOT NULL,
    [Bill-to Customer No_] nvarchar(20) NOT NULL,
    [Creation Date] datetime NOT NULL,
    [Starting Date] datetime NOT NULL,
    [Ending Date] datetime NOT NULL,
    [Status] int NOT NULL,
    [Person Responsible] nvarchar(20) NOT NULL,
    [Global Dimension 1 Code] nvarchar(20) NOT NULL,
    [Global Dimension 2 Code] nvarchar(20) NOT NULL,
    [Job Posting Group] nvarchar(20) NOT NULL,
    [Blocked] int NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job Task] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Job Task Type] int NOT NULL,
    [Indentation] int NOT NULL,
    [Totaling] nvarchar(250) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job Task$0] PRIMARY KEY CLUSTERED ([Job No_], [Job Task No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job Planning Line] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Line No_] int NOT NULL,
    [Line Type] int NOT NULL,
    [Planning Date] datetime NOT NULL,
    [Document No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Quantity] decimal(38,20) NOT NULL,
    [Unit of Measure Code] nvarchar(10) NOT NULL,
    [Unit Cost (LCY)] decimal(38,20) NOT NULL,
    [Total Cost (LCY)] decimal(38,20) NOT NULL,
    [Unit Price (LCY)] decimal(38,20) NOT NULL,
    [Line Amount (LCY)] decimal(38,20) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job Planning Line$0] PRIMARY KEY CLUSTERED ([Job No_], [Job Task No_], [Line No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] (
    [timestamp] rowversion NOT NULL,
    [Entry No_] int NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Posting Date] datetime NOT NULL,
    [Document No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Quantity] decimal(38,20) NOT NULL,
    [Unit Cost (LCY)] decimal(38,20) NOT NULL,
    [Total Cost (LCY)] decimal(38,20) NOT NULL,
    [Unit Price (LCY)] decimal(38,20) NOT NULL,
    [Total Price (LCY)] decimal(38,20) NOT NULL,
    [Line Amount (LCY)] decimal(38,20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Entry Type] int NOT NULL,
    [Global Dimension 1 Code] nvarchar(20) NOT NULL,
    [Global Dimension 2 Code] nvarchar(20) NOT NULL,
    [Unit of Measure Code] nvarchar(10) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job Ledger Entry$0] PRIMARY KEY CLUSTERED ([Entry No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] (
    [timestamp] rowversion NOT NULL,
    [Entry No_] int NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Measurement Date] datetime NOT NULL,
    [Completion _] decimal(38,20) NOT NULL,
    [Measured By] nvarchar(20) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job Progress Entry$0] PRIMARY KEY CLUSTERED ([Entry No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO$Job Milestone] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Code] nvarchar(10) NOT NULL,
    [Sequence] int NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Planned Date] datetime NOT NULL,
    [Actual Date] datetime NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO$Job Milestone$0] PRIMARY KEY CLUSTERED ([Job No_], [Code])
);
GO

-- Tablas de la empresa CONSTRUCTORA DEMO PRUEBAS
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Customer] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Search Name] nvarchar(50) NOT NULL,
    [City] nvarchar(30) NOT NULL,
    [VAT Registration No_] nvarchar(20) NOT NULL,
    [Blocked] int NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Customer$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Resource] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Base Unit of Measure] nvarchar(10) NOT NULL,
    [Unit Cost] decimal(38,20) NOT NULL,
    [Blocked] tinyint NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Resource$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Dimension Value] (
    [timestamp] rowversion NOT NULL,
    [Dimension Code] nvarchar(20) NOT NULL,
    [Code] nvarchar(20) NOT NULL,
    [Name] nvarchar(50) NOT NULL,
    [Dimension Value Type] int NOT NULL,
    [Blocked] tinyint NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Dimension Value$0] PRIMARY KEY CLUSTERED ([Dimension Code], [Code])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job] (
    [timestamp] rowversion NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Search Description] nvarchar(50) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Description 2] nvarchar(50) NOT NULL,
    [Bill-to Customer No_] nvarchar(20) NOT NULL,
    [Creation Date] datetime NOT NULL,
    [Starting Date] datetime NOT NULL,
    [Ending Date] datetime NOT NULL,
    [Status] int NOT NULL,
    [Person Responsible] nvarchar(20) NOT NULL,
    [Global Dimension 1 Code] nvarchar(20) NOT NULL,
    [Global Dimension 2 Code] nvarchar(20) NOT NULL,
    [Job Posting Group] nvarchar(20) NOT NULL,
    [Blocked] int NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job$0] PRIMARY KEY CLUSTERED ([No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job Task] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Job Task Type] int NOT NULL,
    [Indentation] int NOT NULL,
    [Totaling] nvarchar(250) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job Task$0] PRIMARY KEY CLUSTERED ([Job No_], [Job Task No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job Planning Line] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Line No_] int NOT NULL,
    [Line Type] int NOT NULL,
    [Planning Date] datetime NOT NULL,
    [Document No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Quantity] decimal(38,20) NOT NULL,
    [Unit of Measure Code] nvarchar(10) NOT NULL,
    [Unit Cost (LCY)] decimal(38,20) NOT NULL,
    [Total Cost (LCY)] decimal(38,20) NOT NULL,
    [Unit Price (LCY)] decimal(38,20) NOT NULL,
    [Line Amount (LCY)] decimal(38,20) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job Planning Line$0] PRIMARY KEY CLUSTERED ([Job No_], [Job Task No_], [Line No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job Ledger Entry] (
    [timestamp] rowversion NOT NULL,
    [Entry No_] int NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Posting Date] datetime NOT NULL,
    [Document No_] nvarchar(20) NOT NULL,
    [Type] int NOT NULL,
    [No_] nvarchar(20) NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Quantity] decimal(38,20) NOT NULL,
    [Unit Cost (LCY)] decimal(38,20) NOT NULL,
    [Total Cost (LCY)] decimal(38,20) NOT NULL,
    [Unit Price (LCY)] decimal(38,20) NOT NULL,
    [Total Price (LCY)] decimal(38,20) NOT NULL,
    [Line Amount (LCY)] decimal(38,20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Entry Type] int NOT NULL,
    [Global Dimension 1 Code] nvarchar(20) NOT NULL,
    [Global Dimension 2 Code] nvarchar(20) NOT NULL,
    [Unit of Measure Code] nvarchar(10) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job Ledger Entry$0] PRIMARY KEY CLUSTERED ([Entry No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job Progress Entry] (
    [timestamp] rowversion NOT NULL,
    [Entry No_] int NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Job Task No_] nvarchar(20) NOT NULL,
    [Measurement Date] datetime NOT NULL,
    [Completion _] decimal(38,20) NOT NULL,
    [Measured By] nvarchar(20) NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job Progress Entry$0] PRIMARY KEY CLUSTERED ([Entry No_])
);
CREATE TABLE [dbo].[CONSTRUCTORA DEMO PRUEBAS$Job Milestone] (
    [timestamp] rowversion NOT NULL,
    [Job No_] nvarchar(20) NOT NULL,
    [Code] nvarchar(10) NOT NULL,
    [Sequence] int NOT NULL,
    [Description] nvarchar(50) NOT NULL,
    [Planned Date] datetime NOT NULL,
    [Actual Date] datetime NOT NULL,
    CONSTRAINT [CONSTRUCTORA DEMO PRUEBAS$Job Milestone$0] PRIMARY KEY CLUSTERED ([Job No_], [Code])
);
GO

-- Esquema para las vistas de reporting (lo rellena el agente en la demo)
CREATE SCHEMA [rpt] AUTHORIZATION [dbo];
GO
