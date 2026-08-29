import http from '@/api/http';

export type AssistantProvider = 'gemini' | 'groq';
export interface AssistantMessage {
    role: 'user' | 'assistant';
    content: string;
}

export interface WorkshopItem {
    workshopId: string;
    name: string;
    image: string | null;
    description: string;
    modIds: string[];
    updatedAt: number | null;
}

export interface WorkshopConfiguration {
    path: string;
    revision: string;
    workshopItems: string[];
    mods: string[];
    details: WorkshopItem[];
    detailsError: string | null;
}

export interface CurseForgeMod {
    id: number;
    name: string;
    summary: string;
    image: string | null;
    website: string | null;
    downloadCount: number;
    updatedAt: string | null;
    description?: string;
}

export interface CurseForgeFile {
    id: number;
    displayName: string;
    fileName: string;
    fileDate: string | null;
    fileLength: number;
    releaseType: number;
    gameVersions: string[];
    downloadUrl: string | null;
}

const workshopItem = (data: any): WorkshopItem => ({
    workshopId: data.workshop_id,
    name: data.name,
    image: data.image,
    description: data.description,
    modIds: data.mod_ids || [],
    updatedAt: data.updated_at,
});

const curseForgeMod = (data: any): CurseForgeMod => ({
    id: data.id,
    name: data.name,
    summary: data.summary,
    image: data.image,
    website: data.website,
    downloadCount: data.download_count,
    updatedAt: data.updated_at,
    description: data.description,
});

const curseForgeFile = (data: any): CurseForgeFile => ({
    id: data.id,
    displayName: data.display_name,
    fileName: data.file_name,
    fileDate: data.file_date,
    fileLength: data.file_length,
    releaseType: data.release_type,
    gameVersions: data.game_versions || [],
    downloadUrl: data.download_url,
});

export const askAssistant = async (
    uuid: string,
    provider: AssistantProvider,
    message: string,
    history: AssistantMessage[]
): Promise<AssistantMessage> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/howtoo/assistant`, {
        provider,
        message,
        history,
        section: window.location.pathname.split('/').pop(),
    });

    return { role: 'assistant', content: data.answer };
};

export const getWorkshopConfiguration = async (uuid: string): Promise<WorkshopConfiguration> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/workshop`);

    return {
        path: data.path,
        revision: data.revision,
        workshopItems: data.workshop_items || [],
        mods: data.mods || [],
        details: (data.details || []).map(workshopItem),
        detailsError: data.details_error,
    };
};

export const searchWorkshop = async (uuid: string, query: string): Promise<WorkshopItem[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/workshop/search`, { params: { query } });
    return (data.items || []).map(workshopItem);
};

export const saveWorkshop = async (
    uuid: string,
    configuration: Pick<WorkshopConfiguration, 'workshopItems' | 'mods' | 'revision'>,
    restart: boolean
): Promise<WorkshopConfiguration & { restarted: boolean; restartError: string | null }> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/howtoo/workshop`, {
        workshop_items: configuration.workshopItems,
        mods: configuration.mods,
        revision: configuration.revision,
        action: restart ? 'restart' : 'save',
    });

    return {
        path: data.path,
        revision: data.revision,
        workshopItems: data.workshop_items,
        mods: data.mods,
        details: [],
        detailsError: null,
        restarted: data.restarted,
        restartError: data.restart_error,
    };
};

export const getInstalledCurseForgeMods = async (uuid: string) => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/curseforge/installed`);
    return data.items as Array<{ name: string; size: number; modified_at: string | null }>;
};

export const searchCurseForge = async (uuid: string, query: string): Promise<CurseForgeMod[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/curseforge/search`, { params: { query } });
    return (data.items || []).map(curseForgeMod);
};

export const getCurseForgeFiles = async (uuid: string, modId: number): Promise<CurseForgeFile[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/curseforge/mods/${modId}/files`);
    return (data.items || []).map(curseForgeFile);
};

export const getCurseForgeMod = async (uuid: string, modId: number): Promise<CurseForgeMod> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/howtoo/curseforge/mods/${modId}`);
    return curseForgeMod(data);
};

export const installCurseForgeFile = async (uuid: string, modId: number, fileId: number): Promise<string> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/howtoo/curseforge/install`, {
        mod_id: modId,
        file_id: fileId,
    });
    return data.file_name;
};
