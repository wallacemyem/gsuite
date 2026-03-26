<?php

namespace BrickServers\GoogleWorkspace\Enums;

enum ApiScope: string
{
    // Directory API
    case DIRECTORY_USER = 'https://www.googleapis.com/auth/admin.directory.user';
    case DIRECTORY_USER_READONLY = 'https://www.googleapis.com/auth/admin.directory.user.readonly';
    case DIRECTORY_GROUP = 'https://www.googleapis.com/auth/admin.directory.group';
    case DIRECTORY_GROUP_READONLY = 'https://www.googleapis.com/auth/admin.directory.group.readonly';
    case DIRECTORY_ORGUNIT = 'https://www.googleapis.com/auth/admin.directory.orgunit';
    case DIRECTORY_DEVICE_CHROMEOS = 'https://www.googleapis.com/auth/admin.directory.device.chromeos';
    case DIRECTORY_DEVICE_MOBILE = 'https://www.googleapis.com/auth/admin.directory.device.mobile';

    // Classroom API
    case CLASSROOM_COURSES = 'https://www.googleapis.com/auth/classroom.courses';
    case CLASSROOM_ROSTERS = 'https://www.googleapis.com/auth/classroom.rosters';
    case CLASSROOM_TOPICS = 'https://www.googleapis.com/auth/classroom.topics';

    // Calendar API
    case CALENDAR = 'https://www.googleapis.com/auth/calendar';

    // Gmail API
    case GMAIL_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';
    case GMAIL_MODIFY = 'https://www.googleapis.com/auth/gmail.modify';

    // Drive API
    case DRIVE = 'https://www.googleapis.com/auth/drive';
}
